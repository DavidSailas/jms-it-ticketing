<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Ticket;
use App\Models\User;
use App\Support\Activity;
use App\Support\Notifier;
use App\Support\SpreadsheetReader;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public const DEFAULT_PASSWORD = 'P@ssw0rd123';

    /**
     * Roles the signed-in person can see in the Users list and open.
     * Admin       -> users and IT support of their own company
     * JMS admin   -> JMS's own IT Support (our engineers)
     * Super admin -> users, IT support and admins
     */
    private function visibleRoles(): array
    {
        return auth()->user()->manageableRoles();
    }

    private function isSuper(): bool
    {
        return auth()->user()->role === 'super_admin';
    }

    /** An admin of JMS One IT itself (not a partner's admin). */
    private function isJmsAdmin(): bool
    {
        return auth()->user()->isJmsAdmin();
    }

    /**
     * The company box offers "JMS One IT (our team)" (value "jms") for Admin and IT Support.
     * Turn it into "no partner company" and remember the choice, so the rules below can tell it apart from an empty box.
     */
    private function readJmsChoice(Request $request): void
    {
        if ($request->input('company_id') === 'jms') {
            $request->merge(['jms_team' => true, 'company_id' => null]);
        }
    }

    /** Only Admin and IT Support can belong to JMS itself; partner users always belong to a partner company. */
    private function jmsTeamRoleError(Request $request, ?string $role): ?string
    {
        return $request->boolean('jms_team') && ! in_array($role, ['admin', 'it_support'])
            ? 'Partner users must belong to a partner company. Only Admin and IT Support can join the JMS One IT team.'
            : null;
    }

    /**
     * Every account the signed-in person may see: the visible roles, limited to their own company
     * (super admins see all companies).
     */
    private function scoped()
    {
        return User::query()->whereIn('role', $this->visibleRoles())->inMyCompany();
    }

    /** 404/403 unless the account is one this person is allowed to manage. */
    private function authorizeTarget(User $user, int $code = 403): void
    {
        abort_unless($this->scoped()->whereKey($user->id)->exists(), $code);
    }

    /** Roles the signed-in person may create / manage: the same ones they can see, so nobody creates an account they cannot find. */
    private function assignable(): array
    {
        return $this->visibleRoles();
    }

    /**
     * Super admins must pick a company, unless they choose "JMS One IT (our team)". For IT Support, leaving the
     * company empty also makes that person one of JMS's own engineers, who can be assigned to any partner's ticket.
     */
    private function companyRule(Request $request): array
    {
        return [
            'nullable',
            Rule::requiredIf(fn () => $this->isSuper() && $request->input('role') !== 'it_support' && ! $request->boolean('jms_team')),
            Rule::exists('companies', 'id'),
        ];
    }

    public function index(Request $request)
    {
        $visible = $this->visibleRoles();
        $counts  = $this->scoped()->selectRaw('role, count(*) as total')->groupBy('role')->pluck('total', 'role');

        $sort = in_array($request->query('sort'), ['name', 'role', 'created_at', 'tickets_count']) ? $request->query('sort') : 'name';
        $dir  = $request->query('dir') === 'desc' ? 'desc' : ($request->query('dir') === 'asc' ? 'asc' : ($sort === 'created_at' || $sort === 'tickets_count' ? 'desc' : 'asc'));
        $perPage = in_array((int) $request->query('per_page'), [10, 25, 50]) ? (int) $request->query('per_page') : 10;

        $users = $this->scoped()->with('companyRecord:id,name')->withCount('tickets')
            ->when($this->isSuper() && $request->company, fn ($q, $c) => $q->where('company_id', (int) $c))
            ->when(in_array($request->role, $visible), fn ($q) => $q->where('role', $request->role))
            ->when($request->search, fn ($q, $s) => $q->where(fn ($y) =>
                $y->where('name', 'like', "%$s%")
                  ->orWhere('username', 'like', "%$s%")
                  ->orWhere('email', 'like', "%$s%")
                  ->orWhere('users.company', 'like', "%$s%")))
            ->orderBy($sort, $dir)->orderBy('id')
            ->paginate($perPage)->onEachSide(1)->withQueryString();

        return view('users.index', ['users' => $users, 'roles' => $this->assignable(), 'counts' => $counts]);
    }

    /** Everything about one account: details, ticket summary, recent tickets and recent activity. */
    public function show(User $user)
    {
        $this->authorizeTarget($user, 404);

        // Which tickets belong to this person depends on what they do.
        $base = match ($user->role) {
            'it_support' => Ticket::where('assigned_to', $user->id),
            'admin'      => Ticket::where('accepted_by', $user->id),
            default      => Ticket::where('user_id', $user->id),
        };

        $byStatus = (clone $base)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $summary  = [
            'total'     => $byStatus->sum(),
            'active'    => collect(Ticket::ACTIVE)->sum(fn ($s) => $byStatus[$s] ?? 0),
            'done'      => ($byStatus['resolved'] ?? 0) + ($byStatus['closed'] ?? 0),
            'cancelled' => $byStatus['cancelled'] ?? 0,
            'rating'    => $user->role === 'it_support' ? (clone $base)->whereNotNull('rating')->avg('rating') : null,
        ];

        $logs = fn () => $user->activityLogs()->orderByDesc('created_at')->orderByDesc('id');

        return view('users.show', [
            'user'       => $user,
            'roles'      => $this->assignable(),
            'summary'    => $summary,
            'recent'     => (clone $base)->with(['user', 'assignee'])->latest()->limit(6)->get(),
            'activity'   => $logs()->limit(10)->get(),
            'lastLogin'  => $logs()->where('action', 'login')->first(),
            'failed7d'   => $user->activityLogs()->where('action', 'login_failed')->where('created_at', '>=', now()->subDays(7))->count(),
        ]);
    }

    /**
     * An admin sets (or replaces) the profile photo of someone they manage: a partner's admin for their company's
     * staff, a super admin for anyone but other super admins. Same rules as the person's own upload on /profile.
     */
    public function updateAvatar(Request $request, User $user)
    {
        $this->authorizeTarget($user);

        $check = Validator::make($request->all(), [
            'avatar' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ], [
            'avatar.required' => 'Choose a photo to upload.',
            'avatar.file'     => 'The upload did not complete. Please try again.',
            'avatar.mimes'    => 'The photo must be a JPG, PNG or WebP image.',
            'avatar.max'      => 'The photo is too large. Please choose one under 2 MB.',
            'avatar.uploaded' => 'The photo could not be uploaded. It may be larger than the server allows.',
        ]);

        // The Users page has no spot for field errors, so show the reason at the top of the page.
        if ($check->fails()) {
            return back()->with('error', "The photo for {$user->name} was not changed. " . $check->errors()->first('avatar'));
        }

        $path = $request->file('avatar')->store('avatars', 'local');

        if ($user->avatar) {
            Storage::disk('local')->delete($user->avatar);
        }

        $user->forceFill(['avatar' => $path])->save();

        $actor = $request->user();
        Activity::record($actor, 'avatar_updated', "Changed the profile photo of {$user->name}");
        Activity::record($user, 'avatar_updated', "Your profile photo was changed by {$actor->name}");

        return back()->with('success', "{$user->name}'s profile photo was updated.");
    }

    public function destroyAvatar(Request $request, User $user)
    {
        $this->authorizeTarget($user);

        if ($user->avatar) {
            Storage::disk('local')->delete($user->avatar);
            $user->forceFill(['avatar' => null])->save();

            $actor = $request->user();
            Activity::record($actor, 'avatar_removed', "Removed the profile photo of {$user->name}");
            Activity::record($user, 'avatar_removed', "Your profile photo was removed by {$actor->name}");
        }

        return back()->with('success', "{$user->name}'s profile photo was removed.");
    }

    public function store(Request $request)
    {
        $this->readJmsChoice($request);

        $request->merge([
            'username' => Str::lower(trim((string) $request->input('username'))),
            'email'    => Str::lower(trim((string) $request->input('email'))),
        ]);

        $data = $request->validate([
            'name'     => ['required', 'string', 'min:2', 'max:100'],
            'username' => ['required', 'string', 'min:3', 'max:30', 'regex:/^[a-z0-9._-]+$/', 'unique:users,username'],
            'company_id' => $this->companyRule($request),
            'email'    => ['required', 'email:rfc', 'max:255', 'unique:users,email'],
            'role'     => ['required', Rule::in($this->assignable())],
        ], [
            'company_id.required' => 'Choose the company this person belongs to, or "JMS One IT (our team)" for our own admins and engineers.',
            'company_id.exists'   => 'Choose a company from the list.',
            'name.required'     => "Enter the person's full name.",
            'name.min'          => 'The name must be at least 2 characters.',
            'username.required' => 'Choose a username.',
            'username.min'      => 'The username must be at least 3 characters.',
            'username.max'      => 'The username can be at most 30 characters.',
            'username.regex'    => 'Use only lowercase letters, numbers, dots, dashes and underscores (no spaces or @).',
            'username.unique'   => 'That username is already taken.',
            'email.required'    => 'Enter an email address.',
            'email.email'       => 'Enter a valid email address, like name@company.com.',
            'email.unique'      => 'An account with this email already exists.',
            'role.required'     => 'Choose a role for this account.',
            'role.in'           => 'You are not allowed to assign that role.',
        ]);

        if ($error = $this->jmsTeamRoleError($request, $data['role'])) {
            return back()->withErrors(['company_id' => $error])->withInput(array_merge($request->all(), ['company_id' => 'jms']));
        }

        // A JMS admin adds people to JMS's own team; a partner's admin can only add people to their own company.
        if ($this->isJmsAdmin()) {
            $data['company_id'] = null;
        } elseif (! $this->isSuper()) {
            abort_unless(auth()->user()->company_id, 403, 'Your account is not attached to a company yet. Ask JMS to assign one.');
            $data['company_id'] = auth()->user()->company_id;
        }

        User::create($data + ['password' => Hash::make(self::DEFAULT_PASSWORD)])
            ->forceFill(['email_verified_at' => now()])->save();

        return back()->with('success', "Account created. Username: {$data['username']} - Default password: " . self::DEFAULT_PASSWORD);
    }

    /** A ready-to-fill Excel template (or a CSV one with ?format=csv) so admins know the exact columns. */
    public function importTemplate(Request $request)
    {
        if ($request->query('format') !== 'csv') {
            return response()->download(resource_path('templates/users-import-template.xlsx'), 'users-import-template.xlsx');
        }

        $csv = "name,email,username,company,role\n"
             . "Juan Dela Cruz,juan@partner.com,juan.delacruz,Partner Company Inc.,user\n"
             . "Maria Santos,maria@jmsoneit.com,maria.santos,JMS One IT,it_support\n";

        return response()->streamDownload(fn () => print("\xEF\xBB\xBF" . $csv), 'users-import-template.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * Bulk-create accounts from an Excel (.xlsx) or CSV file.
     * Columns: name, email (required) + username, company, role (optional). Bad rows are skipped and reported.
     */
    public function import(Request $request)
    {
        $request->validate(['file' => ['required', 'file', 'extensions:xlsx,xls,csv,txt', 'max:4096']], [
            'file.required'   => 'Choose an Excel (.xlsx) or CSV file to import.',
            'file.extensions' => 'The file must be an Excel (.xlsx) or CSV file.',
            'file.max'        => 'The file is too large (4 MB maximum).',
        ]);

        $file = $request->file('file');

        try {
            $sheet = SpreadsheetReader::read($file->getRealPath(), $file->getClientOriginalExtension());
        } catch (\RuntimeException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }

        // The first non-empty row holds the column headings; keep the other rows keyed by their row number.
        $headerKey = array_key_first($sheet);
        $headerCells = $headerKey === null ? [] : $sheet[$headerKey];
        unset($sheet[$headerKey]);

        $header = array_map(fn ($h) => Str::of((string) $h)->replace("\xEF\xBB\xBF", '')->trim()->lower()->replace([' ', '-'], '_')->toString(), $headerCells);

        if (! in_array('name', $header) || ! in_array('email', $header)) {
            return back()->withErrors(['file' => 'The first row must contain the column headings "name" and "email". Download the template to see the format.']);
        }

        $roleAliases = [
            'user' => 'user', 'partner' => 'user', 'staff' => 'user',
            'it_support' => 'it_support', 'itsupport' => 'it_support', 'engineer' => 'it_support', 'it_engineer' => 'it_support',
            'admin' => 'admin', 'super_admin' => 'super_admin', 'superadmin' => 'super_admin',
        ];

        // Super admins name the company on each row; a company admin always imports into their own company.
        $companies = Company::pluck('id', 'name')->mapWithKeys(fn ($id, $name) => [Str::lower($name) => $id]);
        $myCompany = auth()->user()->company_id;

        if (! $this->isSuper() && ! $this->isJmsAdmin() && ! $myCompany) {
            return back()->withErrors(['file' => 'Your account is not attached to a company yet. Ask JMS to assign one.']);
        }

        $password = Hash::make(self::DEFAULT_PASSWORD);
        $seenEmails = [];
        $seenUsernames = [];
        $created = 0;
        $errors = [];
        $rowCount = 0;

        foreach ($sheet as $line => $cells) {
            if (++$rowCount > 500) { $errors[] = "Stopped at row {$line}: import at most 500 users at a time."; break; }

            $row = [];
            foreach ($header as $i => $key) $row[$key] = trim((string) ($cells[$i] ?? ''));

            $email = Str::lower($row['email'] ?? '');
            $role = $roleAliases[Str::of($row['role'] ?? '')->lower()->trim()->replace([' ', '-'], '_')->toString()] ?? (($row['role'] ?? '') === '' ? (in_array('user', $this->assignable()) ? 'user' : 'it_support') : null);

            if ($role === null || ! in_array($role, $this->assignable())) {
                $errors[] = "Row {$line} ({$email}): role \"" . ($row['role'] ?? '') . '" is not valid or you are not allowed to assign it.';
                continue;
            }

            $username = Str::lower($row['username'] ?? '');
            $autoUsername = $username === '';
            if ($autoUsername) {
                $username = preg_replace('/[^a-z0-9._-]/', '', Str::lower(Str::before($email, '@'))) ?: 'user';
                $username = str_pad($username, 3, '0');
                $base = $username;
                for ($n = 2; User::where('username', $username)->exists() || isset($seenUsernames[$username]); $n++) {
                    $username = $base . $n;
                }
            }

            // "JMS One IT" in the company column (or an empty one for IT Support) means JMS's own team.
            $namesJms     = \App\Models\Company::isJmsName($row['company'] ?? '');
            $companyId    = $this->isSuper() ? ($companies[Str::lower($row['company'] ?? '')] ?? null) : $myCompany;
            $joinsJmsTeam = $this->isJmsAdmin()
                || ($this->isSuper() && $namesJms && in_array($role, ['admin', 'it_support']))
                || ($this->isSuper() && $role === 'it_support' && ($row['company'] ?? '') === '');
            if ($joinsJmsTeam) {
                $companyId = null; // JMS's own team has no partner company
            }
            if (! $companyId && ! $joinsJmsTeam) {
                $errors[] = "Row {$line} ({$email}): the company \"" . ($row['company'] ?? '') . '" does not exist. Create it under Companies first.';
                continue;
            }

            $data = ['name' => $row['name'] ?? '', 'email' => $email, 'username' => $username, 'company_id' => $companyId, 'role' => $role];

            $v = Validator::make($data, [
                'name'     => ['required', 'string', 'min:2', 'max:100'],
                'email'    => ['required', 'email:rfc', 'max:255', 'unique:users,email'],
                'username' => ['required', 'string', 'min:3', 'max:30', 'regex:/^[a-z0-9._-]+$/', 'unique:users,username'],
            ], [
                'email.unique'    => 'an account with this email already exists',
                'username.unique' => 'that username is already taken',
                'username.regex'  => 'username may only use lowercase letters, numbers, . - _',
            ]);

            if ($v->fails()) {
                $errors[] = "Row {$line} (" . ($email ?: 'no email') . '): ' . $v->errors()->first();
                continue;
            }
            if (isset($seenEmails[$email])) {
                $errors[] = "Row {$line} ({$email}): this email appears more than once in the file.";
                continue;
            }
            if (! $autoUsername && isset($seenUsernames[$username])) {
                $errors[] = "Row {$line} ({$email}): the username \"{$username}\" appears more than once in the file.";
                continue;
            }

            User::create($data + ['password' => $password])->forceFill(['email_verified_at' => now()])->save();
            $seenEmails[$email] = true;
            $seenUsernames[$username] = true;
            $created++;
        }
        $message = $created
            ? "Imported {$created} " . Str::plural('account', $created) . '. Their default password is ' . self::DEFAULT_PASSWORD . '.'
            : 'No accounts were imported.';

        return redirect()->route('users.index')
            ->with($created ? 'success' : 'error', $message . (count($errors) ? ' ' . count($errors) . ' ' . Str::plural('row', count($errors)) . ' skipped.' : ''))
            ->with('import_errors', $errors);
    }

    /** Edit an account: name, username, email, company and role. */
    public function update(Request $request, User $user)
    {
        $this->authorizeTarget($user);

        // Nobody can change their own role, so they cannot lock themselves out by mistake.
        $isSelf = $user->id === auth()->id();

        $this->readJmsChoice($request);

        $request->merge([
            'username' => Str::lower(trim((string) $request->input('username'))),
            'email'    => Str::lower(trim((string) $request->input('email'))),
        ]);

        $validator = Validator::make($request->all(), [
            'name'     => ['required', 'string', 'min:2', 'max:100'],
            'username' => ['required', 'string', 'min:3', 'max:30', 'regex:/^[a-z0-9._-]+$/', Rule::unique('users', 'username')->ignore($user->id)],
            'company_id' => $this->companyRule($request),
            'email'    => ['required', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'role'     => [$isSelf ? 'nullable' : 'required', Rule::in($this->assignable())],
        ], [
            'name.required'     => "Enter the person's full name.",
            'name.min'          => 'The name must be at least 2 characters.',
            'username.required' => 'Enter a username.',
            'username.min'      => 'The username must be at least 3 characters.',
            'username.max'      => 'The username can be at most 30 characters.',
            'username.regex'    => 'Use only lowercase letters, numbers, dots, dashes and underscores (no spaces or @).',
            'username.unique'   => 'That username is already taken.',
            'email.required'    => 'Enter an email address.',
            'email.email'       => 'Enter a valid email address, like name@company.com.',
            'email.unique'      => 'An account with this email already exists.',
            'role.required'     => 'Choose a role for this account.',
            'role.in'           => 'You are not allowed to assign that role.',
            'company_id.required' => 'Choose the company this person belongs to, or "JMS One IT (our team)" for our own admins and engineers.',
            'company_id.exists'   => 'Choose a company from the list.',
        ]);

        $validator->after(function ($v) use ($request, $user, $isSelf) {
            $role = $isSelf ? $user->role : $request->input('role');
            if (! $v->errors()->has('company_id') && ($error = $this->jmsTeamRoleError($request, $role))) {
                $v->errors()->add('company_id', $error);
            }
        });

        if ($validator->fails()) {
            // Reopen the Edit window with what was typed and the problems found.
            return back()->withErrors($validator, 'edit')->withInput()->with('edit_user_id', $user->id);
        }

        $data = $validator->validated();
        // Only JMS can move someone to another company.
        if (! $this->isSuper()) {
            unset($data['company_id']);
        }

        // An engineer must not be moved away from tickets they are still working on (they would lose sight of them).
        if (array_key_exists('company_id', $data) && (int) ($data['company_id'] ?? 0) !== (int) $user->company_id && ($data['company_id'] ?? null)) {
            $stranded = Ticket::withoutGlobalScopes()->where('assigned_to', $user->id)->whereIn('status', Ticket::ACTIVE)
                ->where(fn ($q) => $q->whereNull('company_id')->orWhere('company_id', '!=', $data['company_id']))->count();

            if ($stranded > 0) {
                return back()->withErrors(['company_id' => "{$user->name} still has {$stranded} active " . Str::plural('ticket', $stranded) . ' from another company. Reassign them first, then move this person.'], 'edit')
                    ->withInput()->with('edit_user_id', $user->id);
            }
        }
        if ($isSelf || empty($data['role'])) {
            unset($data['role']);
        }

        $user->fill($data);
        $labels  = ['name' => 'name', 'username' => 'username', 'email' => 'email', 'company_id' => 'company', 'company' => 'company', 'role' => 'role'];
        $changed = collect(array_keys($user->getDirty()))->map(fn ($f) => $labels[$f] ?? $f)->unique()->values();

        if ($changed->isEmpty()) {
            return back()->with('success', "No changes were made to {$user->name}.");
        }

        $user->save();

        if (! $isSelf) {
            Activity::record($user, 'profile_updated', 'Your account details (' . $changed->implode(', ') . ') were updated by ' . auth()->user()->name);
        }

        return back()->with('success', "{$user->name}'s account was updated (" . $changed->implode(', ') . ').');
    }

    public function resetPassword(User $user)
    {
        $this->authorizeTarget($user);
        $user->update(['password' => Hash::make(self::DEFAULT_PASSWORD)]);
        Notifier::passwordReset($user, auth()->user());
        Activity::record($user, 'password_reset', 'Your password was reset by ' . auth()->user()->name);

        return back()->with('success', "Password for {$user->email} was reset to the default: " . self::DEFAULT_PASSWORD);
    }

    public function destroy(User $user)
    {
        abort_if($user->id === auth()->id(), 403, 'You cannot delete your own account.');
        $this->authorizeTarget($user);
        $user->delete();

        // Deleting from the details page must not send the person back to a page that no longer exists.
        $previous = url()->previous();
        $to = str_contains($previous, '/users/' . $user->id) ? route('users.index') : $previous;

        return redirect($to)->with('success', 'User deleted.');
    }
}
