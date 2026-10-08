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

/** Super admin only: the partner companies, and who belongs to each. */
class CompanyController extends Controller
{
    public function index(Request $request)
    {
        $companies = Company::query()
            ->withCount([
                'members as admins_count'    => fn ($q) => $q->where('role', 'admin'),
                'members as engineers_count' => fn ($q) => $q->where('role', 'it_support'),
                'members as users_count'     => fn ($q) => $q->where('role', 'user'),
                'tickets as open_tickets_count' => fn ($q) => $q->whereIn('status', Ticket::ACTIVE),
            ])
            ->when($request->search, fn ($q, $s) => $q->where('name', 'like', "%$s%"))
            ->orderBy('name')->get();

        // People still waiting to be placed in a company (for example accounts made before companies existed).
        // IT Support and Admins marked "JMS One IT" are JMS's own team on purpose, so they are not "waiting".
        $unassigned = User::whereIn('role', ['user', 'admin'])->whereNull('company_id')
            ->where(fn ($q) => $q->where('role', 'user')->orWhereNull('company')->orWhere('company', '!=', User::JMS_NAME))
            ->count();

        return view('companies.index', compact('companies', 'unassigned'));
    }

    /** Create a company and, optionally, its first admin in one step. */
    public function store(Request $request)
    {
        $request->merge([
            'admin_username' => Str::lower(trim((string) $request->input('admin_username'))),
            'admin_email'    => Str::lower(trim((string) $request->input('admin_email'))),
        ]);

        $withAdmin = filled($request->input('admin_name')) || filled($request->input('admin_email')) || filled($request->input('admin_username'));

        $data = $request->validate([
            'name'    => ['required', 'string', 'min:2', 'max:255', 'unique:companies,name'],
            'phone'   => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:255'],
            'admin_name'     => [$withAdmin ? 'required' : 'nullable', 'string', 'min:2', 'max:100'],
            'admin_email'    => [$withAdmin ? 'required' : 'nullable', 'email:rfc', 'max:255', 'unique:users,email'],
            'admin_username' => [$withAdmin ? 'required' : 'nullable', 'string', 'min:3', 'max:30', 'regex:/^[a-z0-9._-]+$/', 'unique:users,username'],
        ], [
            'name.required'  => 'Enter the company name.',
            'name.unique'    => 'A company with this name already exists.',
            'admin_name.required'     => "Enter the admin's full name.",
            'admin_email.required'    => "Enter the admin's email address.",
            'admin_email.email'       => 'Enter a valid email address, like name@company.com.',
            'admin_email.unique'      => 'An account with this email already exists.',
            'admin_username.required' => 'Choose a username for the admin.',
            'admin_username.regex'    => 'Use only lowercase letters, numbers, dots, dashes and underscores.',
            'admin_username.unique'   => 'That username is already taken.',
        ]);

        $company = DB::transaction(function () use ($data, $withAdmin) {
            $company = Company::create(['name' => trim($data['name']), 'phone' => $data['phone'] ?? null, 'address' => $data['address'] ?? null]);

            if ($withAdmin) {
                User::create([
                    'name'       => $data['admin_name'],
                    'username'   => $data['admin_username'],
                    'email'      => $data['admin_email'],
                    'role'       => 'admin',
                    'company_id' => $company->id,
                    'password'   => Hash::make(UserController::DEFAULT_PASSWORD),
                ])->forceFill(['email_verified_at' => now()])->save();
            }

            return $company;
        });

        $msg = "{$company->name} was created.";
        if ($withAdmin) {
            $msg .= " Admin username: {$data['admin_username']} - Default password: " . UserController::DEFAULT_PASSWORD;
        }

        return redirect()->route('companies.show', $company)->with('success', $msg);
    }

    /** One company and everyone in it. */
    public function show(Request $request, Company $company)
    {
        $role = in_array($request->query('role'), ['admin', 'it_support', 'user']) ? $request->query('role') : null;

        $members = $company->members()->withCount('tickets')
            ->when($role, fn ($q) => $q->where('role', $role))
            ->when($request->search, fn ($q, $s) => $q->where(fn ($y) => $y
                ->where('name', 'like', "%$s%")->orWhere('username', 'like', "%$s%")->orWhere('email', 'like', "%$s%")))
            ->orderByRaw("CASE role WHEN 'admin' THEN 1 WHEN 'it_support' THEN 2 ELSE 3 END")
            ->orderBy('name')->get();

        $counts = $company->members()->selectRaw('role, count(*) as total')->groupBy('role')->pluck('total', 'role');

        $tickets = $company->tickets()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return view('companies.show', [
            'company' => $company,
            'members' => $members,
            'counts'  => $counts,
            'role'    => $role,
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
