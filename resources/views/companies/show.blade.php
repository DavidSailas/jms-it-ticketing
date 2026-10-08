<x-app-layout>
    <x-slot name="header">Company</x-slot>

    @php
        $ok  = 'border-slate-300 focus:border-brand-500 focus:ring-brand-500';
        $bad = 'border-red-400 focus:border-red-500 focus:ring-red-500';
        $badge = [
            'user'       => 'bg-slate-100 text-slate-700 ring-slate-200',
            'it_support' => 'bg-sky-50 text-sky-700 ring-sky-200',
            'admin'      => 'bg-violet-50 text-violet-700 ring-violet-200',
        ];
        $tabs = ['' => 'Everyone', 'admin' => 'Admins', 'it_support' => 'IT Support', 'user' => 'Users'];
        $memberForm  = old('_member') && $errors->any();
        $companyForm = old('_company') && $errors->any();
        $all = $counts->sum();
    @endphp

    <a href="{{ route('companies.index') }}" class="mb-3 inline-flex items-center gap-1 text-sm font-medium text-slate-500 hover:text-brand-700">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m15 18-6-6 6-6"/></svg>
        All companies
    </a>

    <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div class="flex items-center gap-3">
            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-lg font-bold text-brand-700">{{ \Illuminate\Support\Str::upper(mb_substr($company->name, 0, 1)) }}</span>
            <div class="min-w-0">
                <h2 class="truncate text-xl font-extrabold tracking-tight text-brand-800">{{ $company->name }}</h2>
                <p class="text-sm text-slate-500">{{ $all }} {{ \Illuminate\Support\Str::plural('member', $all) }}@if ($company->phone) &middot; {{ $company->phone }}@endif @if ($company->address) &middot; {{ $company->address }}@endif</p>
            </div>
        </div>
        <button type="button" x-data x-on:click="$dispatch('open-modal', 'add-member')"
                class="inline-flex shrink-0 items-center gap-1.5 rounded-lg bg-brand-800 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
            Add member
        </button>
    </div>

    {{-- Ticket summary --}}
    <div class="mb-5 grid grid-cols-3 gap-3">
        @foreach ([['Tickets', $tickets['total'], 'text-slate-900'], ['Active now', $tickets['active'], 'text-amber-600'], ['Resolved', $tickets['done'], 'text-emerald-600']] as [$label, $value, $color])
            <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
                <p class="text-2xl font-extrabold {{ $color }}">{{ $value }}</p>
                <p class="text-xs text-slate-500">{{ $label }}</p>
            </div>
        @endforeach
    </div>

    <div class="grid gap-5 xl:grid-cols-3">
        {{-- Members --}}
        <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm xl:col-span-2">
            <div class="flex flex-col gap-2 border-b border-slate-200 px-5 pt-3 lg:flex-row lg:items-end lg:justify-between">
                <nav class="-mb-px flex gap-1 overflow-x-auto" aria-label="Filter members">
                    @foreach ($tabs as $value => $label)
                        @php($n = $value === '' ? $all : ($counts[$value] ?? 0))
                        @php($active = ($role ?? '') === $value)
                        <a href="{{ request()->fullUrlWithQuery(['role' => $value ?: null]) }}" @if ($active) aria-current="page" @endif
                           class="flex shrink-0 items-center gap-2 border-b-2 px-3 pb-2.5 pt-1 text-sm font-medium transition {{ $active ? 'border-brand-600 text-brand-800' : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700' }}">
                            {{ $label }}
                            <span class="rounded-full px-1.5 py-px text-xs font-semibold {{ $active ? 'bg-brand-100 text-brand-800' : 'bg-slate-100 text-slate-500' }}">{{ $n }}</span>
                        </a>
                    @endforeach
                </nav>
                <form method="GET" class="pb-3 lg:w-64">
                    @if ($role) <input type="hidden" name="role" value="{{ $role }}"> @endif
                    <input name="search" value="{{ request('search') }}" placeholder="Search members" aria-label="Search members"
                           class="w-full rounded-lg border-slate-300 bg-slate-50 py-2 text-sm placeholder:text-slate-400 focus:border-brand-500 focus:bg-white focus:ring-brand-500">
                </form>
            </div>

            @if ($members->isEmpty())
                <div class="px-6 py-14 text-center">
                    <p class="font-semibold text-slate-800">{{ request()->hasAny(['search', 'role']) ? 'No one matches' : 'No members yet' }}</p>
                    <p class="mt-1 text-sm text-slate-500">{{ request()->hasAny(['search', 'role']) ? 'Try another tab or search.' : 'Add the company admin first; they can add the rest.' }}</p>
                </div>
            @else
                <ul class="divide-y divide-slate-100">
                    @foreach ($members as $m)
                        <li>
                            <a href="{{ route('users.show', $m) }}" class="flex items-center gap-3 px-5 py-3 transition hover:bg-brand-50/40">
                                <x-avatar :user="$m" size="h-10 w-10" text="text-sm" />
                                <div class="min-w-0 flex-1">
                                    <p class="truncate font-semibold text-slate-900">{{ $m->name }}</p>
                                    <p class="truncate text-xs text-slate-500">{{ $m->email }} &middot; {{ '@' . $m->username }}</p>
                                </div>
                                <span class="hidden shrink-0 text-xs text-slate-400 sm:block">{{ $m->tickets_count }} {{ \Illuminate\Support\Str::plural('ticket', $m->tickets_count) }}</span>
                                <span class="shrink-0 rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset {{ $badge[$m->role] ?? $badge['user'] }}">{{ $m->roleLabel() }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        {{-- Company details --}}
        <aside class="space-y-5">
            <form method="POST" action="{{ route('companies.update', $company) }}" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                @csrf @method('PATCH')
                <input type="hidden" name="_company" value="1">
                <h3 class="font-semibold text-brand-800">Company details</h3>
                <div class="mt-3 space-y-3">
                    <div>
                        <label for="name" class="mb-1 block text-sm font-medium text-slate-700">Name</label>
                        <input id="name" name="name" value="{{ $companyForm ? old('name') : $company->name }}" required class="w-full rounded-lg {{ $companyForm && $errors->has('name') ? $bad : $ok }}">
                        @if ($companyForm) @error('name') <p role="alert" class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror @endif
                    </div>
                    <div>
                        <label for="phone" class="mb-1 block text-sm font-medium text-slate-700">Phone</label>
                        <input id="phone" name="phone" value="{{ $companyForm ? old('phone') : $company->phone }}" class="w-full rounded-lg {{ $ok }}">
                    </div>
                    <div>
                        <label for="address" class="mb-1 block text-sm font-medium text-slate-700">Address</label>
                        <input id="address" name="address" value="{{ $companyForm ? old('address') : $company->address }}" class="w-full rounded-lg {{ $ok }}">
                    </div>
                </div>
                <button type="submit" class="mt-4 w-full rounded-lg bg-brand-800 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">Save changes</button>
            </form>

            <form method="POST" action="{{ route('companies.destroy', $company) }}" onsubmit="return confirm('Delete {{ addslashes($company->name) }}? This cannot be undone.')"
                  class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                @csrf @method('DELETE')
                <h3 class="font-semibold text-slate-800">Delete company</h3>
                <p class="mt-1 text-sm text-slate-500">Only possible when the company has no people and no tickets.</p>
                <button type="submit" @disabled($all > 0 || $tickets['total'] > 0) class="mt-3 rounded-lg px-4 py-2 text-sm font-semibold text-rose-700 ring-1 ring-rose-200 hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:bg-transparent">Delete company</button>
            </form>
        </aside>
    </div>

    {{-- Name, logo and colour for this company --}}
    <div class="mt-5 max-w-3xl">
        <x-companies.branding-form :company="$company" :action="route('companies.branding', $company)" />
    </div>

    {{-- Add a member to this company --}}
    <x-modal name="add-member" :show="$memberForm" maxWidth="2xl" focusable>
        <form method="POST" action="{{ route('users.store') }}" novalidate x-data="{ loading: false }" @submit="loading = true">
            @csrf
            <input type="hidden" name="_member" value="1">
            <input type="hidden" name="company_id" value="{{ $company->id }}">
            <div class="flex items-start gap-4 border-b border-slate-100 px-6 py-5">
                <div class="min-w-0 flex-1">
                    <h2 class="text-lg font-bold text-brand-800">Add a member to {{ $company->name }}</h2>
                    <p class="mt-0.5 text-sm text-slate-500">New accounts start with the default password <code class="rounded bg-slate-100 px-1.5 py-0.5 text-slate-700">{{ \App\Http\Controllers\UserController::DEFAULT_PASSWORD }}</code>.</p>
                </div>
                <button type="button" x-on:click="$dispatch('close')" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600" aria-label="Close">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>
                </button>
            </div>
            <div class="space-y-4 px-6 py-5">
                @if ($memberForm)
                    <div role="alert" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">Nothing was saved. Please fix the highlighted fields below.</div>
                @endif
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="m-name" class="mb-1 block text-sm font-medium text-slate-700">Full name</label>
                        <input id="m-name" name="name" value="{{ old('name') }}" required class="w-full rounded-lg {{ $memberForm && $errors->has('name') ? $bad : $ok }}">
                        @if ($memberForm) @error('name') <p role="alert" class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror @endif
                    </div>
                    <div>
                        <label for="m-role" class="mb-1 block text-sm font-medium text-slate-700">Role</label>
                        <select id="m-role" name="role" class="w-full rounded-lg {{ $memberForm && $errors->has('role') ? $bad : $ok }}">
                            @foreach (['admin' => 'Admin', 'it_support' => 'IT Support', 'user' => 'User'] as $v => $l)
                                <option value="{{ $v }}" @selected(old('role', 'user') === $v)>{{ $l }}</option>
                            @endforeach
                        </select>
                        @if ($memberForm) @error('role') <p role="alert" class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror @endif
                    </div>
                    <div>
                        <label for="m-email" class="mb-1 block text-sm font-medium text-slate-700">Email</label>
                        <input id="m-email" name="email" type="email" value="{{ old('email') }}" required autocapitalize="none" class="w-full rounded-lg {{ $memberForm && $errors->has('email') ? $bad : $ok }}">
                        @if ($memberForm) @error('email') <p role="alert" class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror @endif
                    </div>
                    <div>
                        <label for="m-username" class="mb-1 block text-sm font-medium text-slate-700">Username</label>
                        <input id="m-username" name="username" value="{{ old('username') }}" required autocapitalize="none" spellcheck="false" class="w-full rounded-lg {{ $memberForm && $errors->has('username') ? $bad : $ok }}">
                        @if ($memberForm) @error('username') <p role="alert" class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror @endif
                    </div>
                </div>
            </div>
            <div class="flex justify-end gap-2 border-t border-slate-100 bg-slate-50/60 px-6 py-4">
                <button type="button" x-on:click="$dispatch('close')" class="rounded-lg bg-white px-4 py-2 text-sm font-medium text-slate-700 ring-1 ring-slate-300 hover:bg-slate-50">Cancel</button>
                <button type="submit" :disabled="loading" class="rounded-lg bg-brand-800 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700 disabled:opacity-60">Add member</button>
            </div>
        </form>
    </x-modal>
</x-app-layout>
