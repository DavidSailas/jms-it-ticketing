<x-app-layout>
    <x-slot name="header">User Management</x-slot>

    @php
        $ok  = 'border-slate-300 focus:border-brand-500 focus:ring-brand-500';
        $bad = 'border-red-400 focus:border-red-500 focus:ring-red-500';
        $currentRole = request('role', '');
        $tabs = ['' => 'All users', 'user' => 'Partners', 'it_support' => 'IT Support', 'admin' => 'Admins', 'super_admin' => 'Super Admins'];
        // Only show the tabs for roles this person is allowed to see.
        $tabs = array_intersect_key($tabs, array_flip(array_merge([''], $roles)));
        $badge = [
            'user'        => 'bg-slate-100 text-slate-700 ring-slate-200',
            'it_support'  => 'bg-sky-50 text-sky-700 ring-sky-200',
            'admin'       => 'bg-violet-50 text-violet-700 ring-violet-200',
            'super_admin' => 'bg-amber-50 text-amber-800 ring-amber-300',
        ];
    @endphp

    <div x-data="{ kind: null, action: '', name: '' }" @keydown.escape.window="kind = null">

        {{-- Page header --}}
        <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 class="text-xl font-extrabold tracking-tight text-brand-800">Manage users</h2>
                <p class="mt-0.5 text-sm text-slate-500">{{ $counts->sum() }} {{ \Illuminate\Support\Str::plural('account', $counts->sum()) }}. Add people, edit their details and choose what each person can do.</p>
            </div>
            <div class="flex shrink-0 gap-2">
                <button type="button" x-on:click="$dispatch('open-modal', 'import-users')"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50">
                    <svg class="h-4 w-4 text-slate-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 16V4m0 0L8 8m4-4 4 4M4 16v3a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-3"/></svg>
                    Import from file
                </button>
                <button type="button" x-on:click="$dispatch('open-modal', 'create-user')"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-brand-800 px-3.5 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
                    Add user
                </button>
            </div>
        </div>

        {{-- Rows skipped by the last import --}}
        @if (session('import_errors'))
            <div class="mb-5 rounded-xl border border-amber-200 bg-amber-50 p-5">
                <h3 class="text-sm font-semibold text-amber-900">Rows that were skipped</h3>
                <p class="mt-0.5 text-xs text-amber-800">Fix these rows in your file and import them again. Rows that were fine have already been added.</p>
                <ul class="mt-3 max-h-48 space-y-1 overflow-y-auto text-sm text-amber-900">
                    @foreach (array_slice(session('import_errors'), 0, 50) as $err) <li class="flex gap-2"><span aria-hidden="true">&bull;</span><span>{{ $err }}</span></li> @endforeach
                </ul>
                @if (count(session('import_errors')) > 50) <p class="mt-2 text-xs text-amber-800">...and {{ count(session('import_errors')) - 50 }} more.</p> @endif
            </div>
        @endif

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">

            {{-- Role tabs + search --}}
            <div class="flex flex-col gap-3 border-b border-slate-200 px-4 pt-3 lg:flex-row lg:items-end lg:justify-between lg:gap-6 lg:px-5">
                <nav class="-mb-px flex gap-1 overflow-x-auto" aria-label="Filter by role">
                    @foreach ($tabs as $value => $label)
                        @php($n = $value === '' ? $counts->sum() : ($counts[$value] ?? 0))
                        @php($active = $currentRole === $value)
                        <a href="{{ request()->fullUrlWithQuery(['role' => $value ?: null, 'page' => null]) }}" @if ($active) aria-current="page" @endif
                           class="flex shrink-0 items-center gap-2 border-b-2 px-3 pb-2.5 pt-1 text-sm font-medium transition {{ $active ? 'border-brand-600 text-brand-800' : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700' }}">
                            {{ $label }}
                            <span class="rounded-full px-1.5 py-px text-xs font-semibold {{ $active ? 'bg-brand-100 text-brand-800' : 'bg-slate-100 text-slate-500' }}">{{ $n }}</span>
                        </a>
                    @endforeach
                </nav>

                <form method="GET" class="pb-3 lg:w-80 lg:shrink-0">
                    @foreach (['role', 'sort', 'dir', 'per_page'] as $keep)
                        @if (request()->filled($keep)) <input type="hidden" name="{{ $keep }}" value="{{ request($keep) }}"> @endif
                    @endforeach
                    <div class="relative">
                        <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m20 20-3.5-3.5"/></svg>
                        <input name="search" value="{{ request('search') }}" placeholder="Search name, username, email or company" aria-label="Search users"
                               class="w-full rounded-lg border-slate-300 bg-slate-50 py-2 pl-9 {{ request()->filled('search') ? 'pr-9' : 'pr-3' }} text-sm placeholder:text-slate-400 focus:border-brand-500 focus:bg-white focus:ring-brand-500">
                        @if (request()->filled('search'))
                            <a href="{{ request()->fullUrlWithQuery(['search' => null, 'page' => null]) }}" class="absolute right-2 top-1/2 -translate-y-1/2 rounded p-1 text-slate-400 hover:bg-slate-200 hover:text-slate-600" aria-label="Clear search">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>
                            </a>
                        @endif
                    </div>
                </form>
            </div>

            @if ($users->isEmpty())
                <div class="px-6 py-16 text-center">
                    <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-slate-400">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0M16 4.5a3.5 3.5 0 0 1 0 7M18.5 14.5A6.5 6.5 0 0 1 21.5 20"/></svg>
                    </span>
                    <p class="mt-3 font-semibold text-slate-800">{{ request()->filled('search') ? 'No one matches your search' : 'No users in this group yet' }}</p>
                    <p class="mt-1 text-sm text-slate-500">{{ request()->filled('search') ? 'Check the spelling or try a different name, username or email.' : 'Add a user to get started.' }}</p>
                    <div class="mt-5 flex justify-center gap-2">
                        @if (request()->hasAny(['search', 'role']))
                            <a href="{{ route('users.index') }}" class="rounded-lg bg-white px-4 py-2 text-sm font-medium text-slate-700 ring-1 ring-slate-300 hover:bg-slate-50">Clear filters</a>
                        @endif
                        <button type="button" x-on:click="$dispatch('open-modal', 'create-user')" class="rounded-lg bg-brand-800 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">Add user</button>
                    </div>
                </div>
            @else
                <x-table-controls :paginator="$users" :options="[
                    ['value' => 'name|asc',          'label' => 'Name A-Z'],
                    ['value' => 'name|desc',         'label' => 'Name Z-A'],
                    ['value' => 'role|asc',          'label' => 'Role'],
                    ['value' => 'tickets_count|desc', 'label' => 'Most tickets'],
                    ['value' => 'created_at|desc',   'label' => 'Newest first'],
                    ['value' => 'created_at|asc',    'label' => 'Oldest first'],
                ]" default="name|asc" noun="user" embedded />
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="border-b border-slate-200 bg-slate-50/80 text-xs font-semibold text-slate-600">
                            <tr>
                                <x-th-sort key="name" :default="true" class="py-2.5 pl-5 pr-3 text-left">User</x-th-sort>
                                <th scope="col" class="hidden px-3 py-2.5 text-left lg:table-cell">Company</th>
                                <x-th-sort key="role">Role</x-th-sort>
                                <x-th-sort key="created_at" class="hidden px-3 py-2.5 text-left xl:table-cell">Added</x-th-sort>
                                <th scope="col" class="py-2.5 pl-3 pr-5 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($users as $u)
                                @php($manageable = in_array($u->role, $roles))
                                @php($payload = ['id' => $u->id, 'name' => $u->name, 'username' => $u->username, 'email' => $u->email, 'company' => $u->company ?? '', 'company_id' => (string) ($u->company_id ?? (in_array($u->role, ['admin', 'it_support']) ? 'jms' : '')), 'role' => $u->role, 'self' => $u->id === auth()->id(), 'action' => route('users.update', $u), 'avatar' => $u->avatarUrl(), 'initials' => $u->initials(), 'photo_url' => route('users.avatar.update', $u), 'photo_remove_url' => route('users.avatar.destroy', $u)])
                                <tr x-data @click="if (! $event.target.closest('a, button, form, select')) window.location = '{{ route('users.show', $u) }}'"
                                    class="group cursor-pointer transition hover:bg-brand-50/40">
                                    <td class="py-3 pl-5 pr-3">
                                        <div class="flex items-center gap-3">
                                            <x-avatar :user="$u" size="h-10 w-10" text="text-sm" />
                                            <div class="min-w-0">
                                                <p class="truncate font-semibold text-slate-900"><a href="{{ route('users.show', $u) }}" class="hover:text-brand-700 hover:underline">{{ $u->name }}</a>@if ($u->id === auth()->id()) <span class="ml-1 rounded bg-brand-50 px-1.5 py-0.5 text-[10px] font-semibold text-brand-700">YOU</span>@endif</p>
                                                <p class="truncate text-xs text-slate-500">{{ $u->email }}</p>
                                                <p class="truncate text-xs text-slate-400">{{ '@' . $u->username }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="hidden px-3 py-3 text-slate-600 lg:table-cell">{{ $u->company ?: '-' }}</td>
                                    <td class="px-3 py-3">
                                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset {{ $badge[$u->role] ?? $badge['user'] }}">
                                            @if ($u->role === 'super_admin' || $u->role === 'admin')
                                                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $u->role === 'super_admin' ? \App\Support\RoleTheme::CROWN : \App\Support\RoleTheme::SHIELD !!}</svg>
                                            @else
                                                <span class="h-1.5 w-1.5 rounded-full bg-current opacity-70" aria-hidden="true"></span>
                                            @endif
                                            {{ $u->roleLabel() }}
                                        </span>
                                    </td>
                                    <td class="hidden whitespace-nowrap px-3 py-3 text-slate-500 xl:table-cell">{{ $u->created_at?->format('M j, Y') }}</td>
                                    <td class="py-3 pl-3 pr-5">
                                        <div class="flex items-center justify-end gap-1.5">
                                            @if ($manageable)
                                                <button type="button" x-on:click="$dispatch('edit-user', @js($payload))" aria-label="Edit {{ $u->name }}"
                                                        class="inline-flex items-center gap-1.5 rounded-lg bg-white px-2.5 py-1.5 text-xs font-semibold text-brand-700 ring-1 ring-brand-200 transition hover:bg-brand-800 hover:text-white hover:ring-brand-800">
                                                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 20h4L19 9a2.8 2.8 0 0 0-4-4L4 16v4z"/><path d="m13.5 6.5 4 4"/></svg>
                                                    Edit
                                                </button>
                                            @endif
                                            <a href="{{ route('users.show', $u) }}" title="View details" aria-label="View details of {{ $u->name }}"
                                               class="rounded-lg p-1.5 text-slate-400 ring-1 ring-slate-200 transition hover:bg-slate-50 hover:text-slate-700">
                                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
                                            </a>
                                            @if ($manageable)
                                                <button type="button"
                                                        x-on:click="kind = 'reset'; action = @js(route('users.reset-password', $u)); name = @js($u->name)"
                                                        title="Reset password" aria-label="Reset password for {{ $u->name }}"
                                                        class="rounded-lg p-1.5 text-slate-400 ring-1 ring-slate-200 transition hover:bg-amber-50 hover:text-amber-600 hover:ring-amber-200">
                                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="8" cy="15" r="4"/><path d="M10.8 12.2 20 3M17 6l3 3M14 9l2 2"/></svg>
                                                </button>
                                                @if ($u->id !== auth()->id())
                                                    <button type="button"
                                                            x-on:click="kind = 'delete'; action = @js(route('users.destroy', $u)); name = @js($u->name)"
                                                            title="Delete user" aria-label="Delete {{ $u->name }}"
                                                            class="rounded-lg p-1.5 text-slate-400 ring-1 ring-slate-200 transition hover:bg-rose-50 hover:text-rose-600 hover:ring-rose-200">
                                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 7h16M10 11v6M14 11v6M6 7l1 12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-12M9 7V4h6v3"/></svg>
                                                    </button>
                                                @endif
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($users->hasPages())
                    <div class="border-t border-slate-200 bg-slate-50/70 px-5 py-3">{{ $users->links() }}</div>
                @endif
            @endif
        </div>

        {{-- Confirm dialog (reset password / delete) --}}
        <div x-show="kind" x-cloak class="fixed inset-0 z-50 flex items-center justify-center px-4" role="dialog" aria-modal="true">
            <div class="absolute inset-0 bg-slate-900/50" x-on:click="kind = null"></div>
            <form method="POST" :action="action" class="relative w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
                @csrf
                <input type="hidden" name="_method" value="DELETE" :disabled="kind !== 'delete'">
                <div class="flex items-start gap-4">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full" :class="kind === 'delete' ? 'bg-rose-50 text-rose-600' : 'bg-brand-50 text-brand-600'">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.3 3.9 2.4 18a2 2 0 0 0 1.7 3h15.8a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/></svg>
                    </span>
                    <div>
                        <h3 class="text-base font-semibold text-slate-900" x-text="kind === 'delete' ? 'Delete this user?' : 'Reset this password?'"></h3>
                        <p class="mt-1 text-sm text-slate-600"><span class="font-medium" x-text="name"></span></p>
                        <p class="mt-2 text-sm text-slate-500" x-show="kind === 'delete'">The account and every ticket they submitted will be permanently removed.</p>
                        <p class="mt-2 text-sm text-slate-500" x-show="kind === 'reset'">Their password will be set back to the default <code class="rounded bg-slate-100 px-1.5 py-0.5 text-slate-700">{{ \App\Http\Controllers\UserController::DEFAULT_PASSWORD }}</code> and they will be notified.</p>
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" x-on:click="kind = null" class="rounded-lg bg-white px-4 py-2 text-sm font-medium text-slate-700 ring-1 ring-slate-300 hover:bg-slate-50">Cancel</button>
                    <button class="rounded-lg px-4 py-2 text-sm font-semibold text-white" :class="kind === 'delete' ? 'bg-rose-600 hover:bg-rose-700' : 'bg-brand-800 hover:bg-brand-700'" x-text="kind === 'delete' ? 'Yes, delete' : 'Yes, reset password'"></button>
                </div>
            </form>
        </div>
    </div>

    {{-- Edit user window (also used on the details page) --}}
    @include('users._edit-modal')

    {{-- Create account modal --}}
    @php($creating = ! session('edit_user_id'))
    <x-modal name="create-user" :show="$creating && $errors->hasAny(['name', 'email', 'username', 'company_id', 'role'])" maxWidth="2xl" focusable>
        <form method="POST" action="{{ route('users.store') }}" novalidate
              x-data="{ loading: false, touched: {{ $creating && old('username') ? 'true' : 'false' }}, username: @js($creating ? old('username', '') : ''), role: @js($creating ? old('role', 'user') : 'user') }" @submit="loading = true">
            @csrf
            <div class="flex items-start gap-4 border-b border-slate-100 px-6 py-5">
                <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-brand-50 text-brand-700">
                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="8" r="4"/><path d="M2.5 21a6.5 6.5 0 0 1 13 0M19 8v6M16 11h6"/></svg>
                </span>
                <div class="min-w-0 flex-1">
                    <h2 class="text-lg font-bold text-brand-800">Add a user</h2>
                    <p class="mt-0.5 text-sm text-slate-500">New accounts start with the default password <code class="rounded bg-slate-100 px-1.5 py-0.5 text-slate-700">{{ \App\Http\Controllers\UserController::DEFAULT_PASSWORD }}</code>. Share it with the person along with their username.</p>
                </div>
                <button type="button" x-on:click="$dispatch('close')" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600" aria-label="Close">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>
                </button>
            </div>

            <div class="space-y-5 px-6 py-5">
                @if ($creating && $errors->any())
                    <div role="alert" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                        Nothing was saved. Please fix the {{ $errors->count() === 1 ? 'highlighted field' : $errors->count() . ' highlighted fields' }} below.
                    </div>
                @endif

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="name" class="mb-1 block text-sm font-medium text-slate-700">Full name</label>
                        <input id="name" name="name" value="{{ $creating ? old('name') : '' }}" required placeholder="Juan Dela Cruz" class="w-full rounded-lg {{ $creating && $errors->has('name') ? $bad : $ok }}">
                        @if ($creating) @error('name') <p role="alert" class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror @endif
                    </div>
                    <div>
                        @if (auth()->user()->role === 'super_admin')
                            <label for="company_id" class="mb-1 block text-sm font-medium text-slate-700">Company</label>
                            <select id="company_id" name="company_id" required class="w-full rounded-lg {{ $creating && $errors->has('company_id') ? $bad : $ok }}">
                                <option value="">Choose a company</option>
                                <option value="jms" x-bind:disabled="role === 'user'" x-bind:hidden="role === 'user'" @selected($creating && (old('jms_team') || old('company_id') === 'jms'))>JMS One IT (our team)</option>
                                @foreach (\App\Models\Company::orderBy('name')->get(['id', 'name']) as $c)
                                    <option value="{{ $c->id }}" @selected($creating && ! old('jms_team') && (string) old('company_id', request('company')) === (string) $c->id)>{{ $c->name }}</option>
                                @endforeach
                            </select>
                            @if ($creating) @error('company_id') <p role="alert" class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror @endif
                            <p x-show="role !== 'user'" x-cloak class="mt-1.5 text-xs text-slate-500">Choose <strong>JMS One IT (our team)</strong> for our own admins and engineers. Our engineers can be assigned to any partner's tickets.</p>
                        @else
                            <label class="mb-1 block text-sm font-medium text-slate-700">Company</label>
                            <p class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-600">{{ auth()->user()->company ?: 'Not set' }}</p>
                        @endif
                    </div>
                    <div>
                        <label for="email" class="mb-1 block text-sm font-medium text-slate-700">Email</label>
                        <input id="email" name="email" type="email" value="{{ $creating ? old('email') : '' }}" required placeholder="name@company.com" autocapitalize="none"
                               @input="if (!touched) username = $event.target.value.split('@')[0].toLowerCase().replace(/[^a-z0-9._-]/g, '')"
                               class="w-full rounded-lg {{ $creating && $errors->has('email') ? $bad : $ok }}">
                        @if ($creating) @error('email') <p role="alert" class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror @endif
                    </div>
                    <div>
                        <label for="username" class="mb-1 block text-sm font-medium text-slate-700">Username</label>
                        <input id="username" name="username" x-model="username" @input="touched = true" required placeholder="juan.delacruz" autocapitalize="none" spellcheck="false"
                               class="w-full rounded-lg {{ $creating && $errors->has('username') ? $bad : $ok }}">
                        @if ($creating && $errors->has('username')) <p role="alert" class="mt-1.5 text-sm text-red-600">{{ $errors->first('username') }}</p> @else <p class="mt-1.5 text-xs text-slate-400">Used to sign in. Filled in from the email, and you can change it.</p> @endif
                    </div>
                </div>

                <x-users.role-picker :roles="$roles" model="role" :error="$creating ? $errors->first('role') : null" />
            </div>

            <div class="flex justify-end gap-2 border-t border-slate-100 bg-slate-50/70 px-6 py-4">
                <button type="button" x-on:click="$dispatch('close')" class="rounded-lg bg-white px-4 py-2 text-sm font-medium text-slate-700 ring-1 ring-slate-300 hover:bg-slate-50">Cancel</button>
                <button type="submit" :disabled="loading" class="rounded-lg bg-brand-800 px-5 py-2 text-sm font-semibold text-white hover:bg-brand-700 disabled:opacity-70">
                    <span x-text="loading ? 'Creating...' : 'Create account'">Create account</span>
                </button>
            </div>
        </form>
    </x-modal>

    {{-- Import modal --}}
    <x-modal name="import-users" :show="$errors->has('file')" maxWidth="lg">
        <form method="POST" action="{{ route('users.import') }}" enctype="multipart/form-data"
              x-data="{ fileName: '', loading: false, drag: false }" @submit="loading = true" class="p-6">
            @csrf
            <div class="mb-5 flex items-start justify-between gap-4">
                <div>
                    <h2 class="text-lg font-bold text-brand-800">Import users</h2>
                    <p class="mt-1 text-sm text-slate-500">Add many accounts at once from an Excel or CSV file. Everyone gets the default password <code class="rounded bg-slate-100 px-1.5 py-0.5 text-slate-700">{{ \App\Http\Controllers\UserController::DEFAULT_PASSWORD }}</code>.</p>
                </div>
                <button type="button" x-on:click="$dispatch('close')" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600" aria-label="Close">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>
                </button>
            </div>

            <ol class="mb-5 space-y-2 text-sm text-slate-600">
                <li class="flex gap-3"><span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-brand-800 text-xs font-bold text-white">1</span>
                    <span><a href="{{ route('users.import-template') }}" class="font-medium text-brand-700 underline">Download the Excel template</a> and fill it in (or use the <a href="{{ route('users.import-template', ['format' => 'csv']) }}" class="font-medium text-brand-700 underline">CSV template</a>).</span></li>
                <li class="flex gap-3"><span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-brand-800 text-xs font-bold text-white">2</span>
                    <span>Required columns: <strong>name</strong>, <strong>email</strong>. Optional: <strong>username</strong> (made from the email if empty), <strong>company</strong> (must match a company under Companies, or write <strong>JMS One IT</strong> for our own admins and engineers; company admins always import into their own), <strong>role</strong> (user, it_support{{ in_array('admin', $roles) ? ', admin' : '' }}{{ in_array('super_admin', $roles) ? ', super_admin' : '' }}; empty means {{ in_array('user', $roles) ? 'user' : 'it_support' }}).</span></li>
                <li class="flex gap-3"><span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-brand-800 text-xs font-bold text-white">3</span>
                    <span>Upload it below. Up to 500 rows. Rows with problems are skipped and listed so you can fix them.</span></li>
            </ol>

            <label class="flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed px-4 py-8 text-center transition"
                   :class="drag ? 'border-brand-500 bg-brand-50' : 'border-slate-300 hover:border-brand-400 hover:bg-slate-50'"
                   @dragover.prevent="drag = true" @dragleave.prevent="drag = false"
                   @drop.prevent="drag = false; $refs.file.files = $event.dataTransfer.files; fileName = $refs.file.files[0]?.name ?? ''">
                <svg class="mb-2 h-8 w-8 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 16V4m0 0L8 8m4-4 4 4M4 16v3a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-3"/></svg>
                <span class="text-sm font-medium text-slate-700" x-text="fileName || 'Click to choose an Excel or CSV file, or drop it here'"></span>
                <span class="mt-1 text-xs text-slate-400">.xlsx or .csv, up to 4 MB</span>
                <input x-ref="file" type="file" name="file" accept=".xlsx,.csv,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" class="sr-only" @change="fileName = $event.target.files[0]?.name ?? ''">
            </label>
            @error('file') <p role="alert" class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror

            <div class="mt-6 flex justify-end gap-2 border-t border-slate-100 pt-5">
                <button type="button" x-on:click="$dispatch('close')" class="rounded-lg bg-white px-4 py-2 text-sm font-medium text-slate-700 ring-1 ring-slate-300 hover:bg-slate-50">Cancel</button>
                <button type="submit" :disabled="! fileName || loading" class="rounded-lg bg-brand-800 px-5 py-2 text-sm font-semibold text-white hover:bg-brand-700 disabled:cursor-not-allowed disabled:opacity-50">
                    <span x-text="loading ? 'Importing...' : 'Import users'">Import users</span>
                </button>
            </div>
        </form>
    </x-modal>
</x-app-layout>
