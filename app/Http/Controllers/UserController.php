<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\User;
use App\Support\Activity;
use App\Support\Notifier;
use App\Support\SpreadsheetReader;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public const DEFAULT_PASSWORD = 'P@ssw0rd123';

    /**
     * Roles the signed-in person can see in the Users list and open.
     * Admin       -> users and IT support
     * Super admin -> users, IT support and admins
     */
    private function visibleRoles(): array
    {
        return auth()->user()->role === 'super_admin'
            ? ['user', 'it_support', 'admin']
            : ['user', 'it_support'];
    }

    /** Roles the signed-in person may create / manage: the same ones they can see, so nobody creates an account they cannot find. */
    private function assignable(): array
    {
        return $this->visibleRoles();
    }

    public function index(Request $request)
    {
        $visible = $this->visibleRoles();
        $counts  = User::whereIn('role', $visible)->selectRaw('role, count(*) as total')->groupBy('role')->pluck('total', 'role');

        $sort = in_array($request->query('sort'), ['name', 'role', 'created_at', 'tickets_count']) ? $request->query('sort') : 'name';
        $dir  = $request->query('dir') === 'desc' ? 'desc' : ($request->query('dir') === 'asc' ? 'asc' : ($sort === 'created_at' || $sort === 'tickets_count' ? 'desc' : 'asc'));
        $perPage = in_array((int) $request->query('per_page'), [10, 25, 50]) ? (int) $request->query('per_page') : 10;

        $users = User::query()->withCount('tickets')
            ->whereIn('role', $visible)
            ->when(in_array($request->role, $visible), fn ($q) => $q->where('role', $request->role))
            ->when($request->search, fn ($q, $s) => $q->where(fn ($y) =>
                $y->where('name', 'like', "%$s%")
                  ->orWhere('username', 'like', "%$s%")
                  ->orWhere('email', 'like', "%$s%")
                  ->orWhere('company', 'like', "%$s%")))
            ->orderBy($sort, $dir)->orderBy('id')
            ->paginate($perPage)->onEachSide(1)->withQueryString();

        return view('users.index', ['users' => $users, 'roles' => $this->assignable(), 'counts' => $counts]);
    }

    /** Everything about one account: details, ticket summary, recent tickets and recent activity. */
    public function show(User $user)
    {
        abort_unless(in_array($user->role, $this->visibleRoles()), 404);

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

    public function store(Request $request)
    {
        $request->merge([
            'username' => Str::lower(trim((string) $request->input('username'))),
            'email'    => Str::lower(trim((string) $request->input('email'))),
        ]);

        $data = $request->validate([
            'name'     => ['required', 'string', 'min:2', 'max:100'],
            'username' => ['required', 'string', 'min:3', 'max:30', 'regex:/^[a-z0-9._-]+$/', 'unique:users,username'],
            'company'  => ['nullable', 'string', 'max:255'],
            'email'    => ['required', 'email:rfc', 'max:255', 'unique:users,email'],
            'role'     => ['required', Rule::in($this->assignable())],
        ], [
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
            $role = $roleAliases[Str::of($row['role'] ?? '')->lower()->trim()->replace([' ', '-'], '_')->toString()] ?? (($row['role'] ?? '') === '' ? 'user' : null);

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

            $data = ['name' => $row['name'] ?? '', 'email' => $email, 'username' => $username, 'company' => ($row['company'] ?? '') ?: null, 'role' => $role];

            $v = Validator::make($data, [
                'name'     => ['required', 'string', 'min:2', 'max:100'],
                'email'    => ['required', 'email:rfc', 'max:255', 'unique:users,email'],
                'username' => ['required', 'string', 'min:3', 'max:30', 'regex:/^[a-z0-9._-]+$/', 'unique:users,username'],
                'company'  => ['nullable', 'string', 'max:255'],
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
        abort_unless(in_array($user->role, $this->assignable()), 403);

        // Nobody can change their own role, so they cannot lock themselves out by mistake.
        $isSelf = $user->id === auth()->id();

        $request->merge([
            'username' => Str::lower(trim((string) $request->input('username'))),
            'email'    => Str::lower(trim((string) $request->input('email'))),
        ]);

        $validator = Validator::make($request->all(), [
            'name'     => ['required', 'string', 'min:2', 'max:100'],
            'username' => ['required', 'string', 'min:3', 'max:30', 'regex:/^[a-z0-9._-]+$/', Rule::unique('users', 'username')->ignore($user->id)],
            'company'  => ['nullable', 'string', 'max:255'],
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
        ]);

        if ($validator->fails()) {
            // Reopen the Edit window with what was typed and the problems found.
            return back()->withErrors($validator, 'edit')->withInput()->with('edit_user_id', $user->id);
        }

        $data = $validator->validated();
        $data['company'] = ($data['company'] ?? '') === '' ? null : $data['company'];
        if ($isSelf || empty($data['role'])) {
            unset($data['role']);
        }

        $user->fill($data);
        $labels  = ['name' => 'name', 'username' => 'username', 'email' => 'email', 'company' => 'company', 'role' => 'role'];
        $changed = collect(array_keys($user->getDirty()))->map(fn ($f) => $labels[$f] ?? $f)->values();

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
        abort_unless(in_array($user->role, $this->assignable()), 403);
        $user->update(['password' => Hash::make(self::DEFAULT_PASSWORD)]);
        Notifier::passwordReset($user, auth()->user());
        Activity::record($user, 'password_reset', 'Your password was reset by ' . auth()->user()->name);

        return back()->with('success', "Password for {$user->email} was reset to the default: " . self::DEFAULT_PASSWORD);
    }

    public function destroy(User $user)
    {
        abort_if($user->id === auth()->id(), 403, 'You cannot delete your own account.');
        abort_unless(in_array($user->role, $this->assignable()), 403);
        $user->delete();

        // Deleting from the details page must not send the person back to a page that no longer exists.
        $previous = url()->previous();
        $to = str_contains($previous, '/users/' . $user->id) ? route('users.index') : $previous;

        return redirect($to)->with('success', 'User deleted.');
    }
}
