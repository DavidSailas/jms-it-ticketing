<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Super admin only: the partner companies, and the customers that belong to each. */
class CompanyController extends Controller
{
    private const BOARD_COLUMNS = ['open' => 'Open', 'assigned' => 'Assigned', 'in_progress' => 'In progress', 'on_hold' => 'On hold', 'resolved' => 'Resolved'];

    public function index(Request $request)
    {
        $companies = Company::query()
            ->withCount([
                'members as users_count'        => fn ($q) => $q->where('role', 'user'),
                'tickets as open_tickets_count' => fn ($q) => $q->whereIn('status', Ticket::ACTIVE),
            ])
            ->when($request->search, fn ($q, $s) => $q->where('name', 'like', "%$s%"))
            ->orderBy('name')->get();

        // JMS One IT is our own company: its card shows the whole JMS team, wherever each account is filed.
        $companies->each(function (Company $c) {
            if (! $c->isJms()) {
                return;
            }
            $c->setAttribute('admins_count', User::jmsTeam()->whereIn('role', ['admin', 'super_admin'])->count());
            $c->setAttribute('engineers_count', User::jmsEngineers()->count());
            $c->setAttribute('users_count', 0);   // our own company has no customers
            $c->setAttribute('open_tickets_count', Ticket::whereIn('status', Ticket::ACTIVE)->whereIn('assigned_to', User::jmsEngineers()->select('id'))->count());
        });

        // Customers still waiting to be placed in a partner company (for example accounts made before companies existed).
        // Admins and IT Engineers are JMS's own team on purpose, so they are never "waiting".
        $unassigned = User::where('role', 'user')->whereNull('company_id')->count();

        return view('companies.index', compact('companies', 'unassigned'));
    }

    /** Create a company and, optionally, its first customer in one step. */
    public function store(Request $request)
    {
        $request->merge([
            'customer_username' => Str::lower(trim((string) $request->input('customer_username'))),
            'customer_email'    => Str::lower(trim((string) $request->input('customer_email'))),
        ]);

        $withCustomer = filled($request->input('customer_name')) || filled($request->input('customer_email')) || filled($request->input('customer_username'));

        $data = $request->validate([
            'name'    => ['required', 'string', 'min:2', 'max:255', 'unique:companies,name'],
            'phone'   => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:255'],
            'customer_name'     => [$withCustomer ? 'required' : 'nullable', 'string', 'min:2', 'max:100'],
            'customer_email'    => [$withCustomer ? 'required' : 'nullable', 'email:rfc', 'max:255', 'unique:users,email'],
            'customer_username' => [$withCustomer ? 'required' : 'nullable', 'string', 'min:3', 'max:30', 'regex:/^[a-z0-9._-]+$/', 'unique:users,username'],
        ], [
            'name.required'  => 'Enter the company name.',
            'name.unique'    => 'A company with this name already exists.',
            'customer_name.required'     => "Enter the customer's full name.",
            'customer_email.required'    => "Enter the customer's email address.",
            'customer_email.email'       => 'Enter a valid email address, like name@company.com.',
            'customer_email.unique'      => 'An account with this email already exists.',
            'customer_username.required' => 'Choose a username for the customer.',
            'customer_username.regex'    => 'Use only lowercase letters, numbers, dots, dashes and underscores.',
            'customer_username.unique'   => 'That username is already taken.',
        ]);

        $company = DB::transaction(function () use ($data, $withCustomer) {
            $company = Company::create(['name' => trim($data['name']), 'phone' => $data['phone'] ?? null, 'address' => $data['address'] ?? null]);

            if ($withCustomer) {
                User::create([
                    'name'       => $data['customer_name'],
                    'username'   => $data['customer_username'],
                    'email'      => $data['customer_email'],
                    'role'       => 'user',
                    'company_id' => $company->id,
                    'password'   => Hash::make(UserController::DEFAULT_PASSWORD),
                ])->forceFill(['email_verified_at' => now()])->save();
            }

            return $company;
        });

        $msg = "{$company->name} was created.";
        if ($withCustomer) {
            $msg .= " Customer username: {$data['customer_username']} - Default password: " . UserController::DEFAULT_PASSWORD;
        }

        return redirect()->route('companies.show', $company)->with('success', $msg);
    }

    /** One company and everyone in it. */
    public function show(Request $request, Company $company)
    {
        $role = in_array($request->query('role'), ['admin', 'it_support', 'user']) ? $request->query('role') : null;

        // Our own company (JMS One IT) is the whole JMS team; a partner company is the people filed under it.
        $isJms  = $company->isJms();
        $people = fn () => $isJms ? User::jmsTeam() : $company->members();

        $members = $people()->withCount(['tickets', 'assignedTickets as active_count' => fn ($q) => $q->whereIn('status', Ticket::ACTIVE)])
            ->when($role, fn ($q) => $isJms && $role === 'admin' ? $q->whereIn('role', ['admin', 'super_admin']) : $q->where('role', $role))
            ->when($request->search, fn ($q, $s) => $q->where(fn ($y) => $y
                ->where('name', 'like', "%$s%")->orWhere('username', 'like', "%$s%")->orWhere('email', 'like', "%$s%")))
            ->orderByRaw("CASE role WHEN 'super_admin' THEN 0 WHEN 'admin' THEN 1 WHEN 'it_support' THEN 2 ELSE 3 END")
            ->orderBy('name')->get();

        $counts = $people()->selectRaw('role, count(*) as total')->groupBy('role')->pluck('total', 'role');
        if ($isJms) {
            // Super admins and JMS admins are both "Admins" on this page.
            $counts = $counts->put('admin', (int) ($counts['admin'] ?? 0) + (int) ($counts['super_admin'] ?? 0))->except('super_admin');
        }

        $team = $isJms ? [
            'active'   => Ticket::whereIn('status', Ticket::ACTIVE)->whereIn('assigned_to', User::jmsEngineers()->select('id'))->count(),
            'resolved' => Ticket::whereIn('assigned_to', User::jmsEngineers()->select('id'))->where('resolved_at', '>=', now()->startOfMonth())->count(),
        ] : null;

        $tickets = $company->tickets()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        // The company's tickets, as a List or a Board (same switch as All Tickets). The three summary tiles filter both.
        $view  = in_array($request->query('tickets'), ['active', 'done']) ? $request->query('tickets') : 'all';
        $style = $request->query('tv') === 'board' ? 'board' : 'list';

        $filtered = fn () => $company->tickets()->with(['user', 'assignee'])
            ->when($view === 'active', fn ($q) => $q->whereIn('status', Ticket::ACTIVE))
            ->when($view === 'done', fn ($q) => $q->whereIn('status', ['resolved', 'closed']))
            ->latest()->latest('id');

        $ticketList = null;
        $board = [];

        if ($isJms) {
            $style = 'list'; // our own company has no ticket list: its tickets are every partner's, see All Tickets
        } elseif ($style === 'list') {
            $ticketList = $filtered()->paginate(10, ['*'], 'tp')->withQueryString()->fragment('tickets');
        } else {
            // Same columns as the All Tickets board; resolved tickets stay on it for 14 days, closed ones live in the list.
            foreach (self::BOARD_COLUMNS as $status => $label) {
                $board[$status] = [
                    'label'   => $label,
                    'total'   => (clone $filtered())->where('status', $status)
                        ->when($status === 'resolved', fn ($q) => $q->where('resolved_at', '>=', now()->subDays(14)))->count(),
                    'tickets' => $filtered()->where('status', $status)
                        ->when($status === 'resolved', fn ($q) => $q->where('resolved_at', '>=', now()->subDays(14)))->limit(20)->get(),
                ];
            }
        }

        $ticketCount   = $isJms ? 0 : ($style === 'list' ? $ticketList->total() : collect($board)->sum('total'));
        $ticketHeading = ['all' => 'All tickets', 'active' => 'Active tickets', 'done' => 'Resolved tickets'][$view];

        // Quick-assign panel: only people who may dispatch JMS engineers get it (see Ticket::assignableEngineers).
        $canAssign = ! $isJms && $request->user()->canDispatchJms()
            && $company->tickets()->whereNotIn('status', ['resolved', 'closed', 'cancelled'])->exists();

        return view('companies.show', [
            'canAssign' => $canAssign,
            'company' => $company,
            'members' => $members,
            'counts'  => $counts,
            'role'    => $role,
            'view'    => $view,
            'isJms'   => $isJms,
            'team'    => $team,
            'style'   => $style,
            'ticketCount' => $ticketCount,
            'ticketHeading' => $ticketHeading,
            'ticketList' => $ticketList,
            'board'   => $board,
            'tickets' => [
                'total'  => $tickets->sum(),
                'active' => collect(Ticket::ACTIVE)->sum(fn ($s) => $tickets[$s] ?? 0),
                'done'   => ($tickets['resolved'] ?? 0) + ($tickets['closed'] ?? 0),
            ],
        ]);
    }

    public function update(Request $request, Company $company)
    {
        $data = $request->validate([
            'name'    => ['required', 'string', 'min:2', 'max:255', Rule::unique('companies', 'name')->ignore($company->id)],
            'phone'   => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:255'],
        ], [
            'name.required' => 'Enter the company name.',
            'name.unique'   => 'A company with this name already exists.',
        ]);

        $company->update(['name' => trim($data['name']), 'phone' => $data['phone'] ?? null, 'address' => $data['address'] ?? null]);

        return back()->with('success', 'Company details saved.');
    }

    /** Only an empty company can be removed, so tickets and accounts are never lost by accident. */
    public function destroy(Company $company)
    {
        if ($company->members()->exists() || $company->tickets()->exists()) {
            return back()->with('error', 'This company still has people or tickets. Remove or move them first.');
        }

        $company->delete();

        return redirect()->route('companies.index')->with('success', 'Company deleted.');
    }
}
