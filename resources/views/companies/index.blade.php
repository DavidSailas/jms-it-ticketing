<x-app-layout>
    <x-slot name="header">Companies</x-slot>

    @php
        $ok  = 'border-slate-300 focus:border-brand-500 focus:ring-brand-500';
        $bad = 'border-red-400 focus:border-red-500 focus:ring-red-500';
        $newCompany = old('_new_company') && $errors->any();
        $total = fn ($c) => $c->admins_count + $c->engineers_count + $c->users_count;
    @endphp

    <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h2 class="text-xl font-extrabold tracking-tight text-brand-800">Partner companies</h2>
            <p class="mt-0.5 text-sm text-slate-500">{{ $companies->count() }} {{ \Illuminate\Support\Str::plural('company', $companies->count()) }}. Create a company and its admin; the admin then adds their own users and IT support.</p>
        </div>
        <button type="button" x-data x-on:click="$dispatch('open-modal', 'create-company')"
                class="inline-flex shrink-0 items-center gap-1.5 rounded-lg bg-brand-800 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
            Add company
        </button>
    </div>

    @if ($unassigned > 0)
        <x-form-alert type="info" class="mb-5">
            {{ $unassigned }} {{ \Illuminate\Support\Str::plural('account', $unassigned) }} {{ $unassigned === 1 ? 'is' : 'are' }} not in any company yet, so
            {{ $unassigned === 1 ? 'it cannot' : 'they cannot' }} see any tickets. Open <a href="{{ route('users.index') }}" class="font-semibold underline">Users</a> and choose a company for each.
        </x-form-alert>
    @endif

    <form method="GET" class="mb-4 max-w-sm">
        <div class="relative">
            <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m20 20-3.5-3.5"/></svg>
            <input name="search" value="{{ request('search') }}" placeholder="Search companies" aria-label="Search companies"
                   class="w-full rounded-lg border-slate-300 bg-white py-2 pl-9 pr-3 text-sm placeholder:text-slate-400 focus:border-brand-500 focus:ring-brand-500">
        </div>
    </form>

    @if ($companies->isEmpty())
        <div class="rounded-xl border border-slate-200 bg-white px-6 py-16 text-center shadow-sm">
            <p class="font-semibold text-slate-800">{{ request()->filled('search') ? 'No company matches your search' : 'No companies yet' }}</p>
            <p class="mt-1 text-sm text-slate-500">{{ request()->filled('search') ? 'Try a different name.' : 'Add your first partner company to get started.' }}</p>
        </div>
    @else
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($companies as $c)
                <a href="{{ route('companies.show', $c) }}"
                   class="group block rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-brand-400 hover:shadow focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500">
                    <div class="flex items-start gap-3">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-base font-bold text-brand-700">{{ \Illuminate\Support\Str::upper(mb_substr($c->name, 0, 1)) }}</span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-semibold text-slate-900 group-hover:text-brand-700">{{ $c->name }}</p>
                            <p class="text-xs text-slate-500">{{ $total($c) }} {{ \Illuminate\Support\Str::plural('member', $total($c)) }}</p>
                        </div>
                        @if ($c->open_tickets_count > 0)
                            <span class="shrink-0 rounded-full bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-700 ring-1 ring-inset ring-amber-200">{{ $c->open_tickets_count }} open</span>
                        @endif
                    </div>
                    <dl class="mt-4 grid grid-cols-3 divide-x divide-slate-100 text-center">
                        <div><dd class="text-lg font-extrabold text-violet-600">{{ $c->admins_count }}</dd><dt class="text-xs text-slate-500">{{ \Illuminate\Support\Str::plural('Admin', $c->admins_count) }}</dt></div>
                        <div><dd class="text-lg font-extrabold text-sky-600">{{ $c->engineers_count }}</dd><dt class="text-xs text-slate-500">IT Support</dt></div>
                        <div><dd class="text-lg font-extrabold text-slate-700">{{ $c->users_count }}</dd><dt class="text-xs text-slate-500">{{ \Illuminate\Support\Str::plural('User', $c->users_count) }}</dt></div>
                    </dl>
                    @if ($c->admins_count === 0)
                        <p class="mt-3 rounded-lg bg-rose-50 px-3 py-1.5 text-xs font-medium text-rose-700">No admin yet. Open the company to add one.</p>
                    @endif
                </a>
            @endforeach
        </div>
    @endif

    {{-- Add company (with its first admin) --}}
    <x-modal name="create-company" :show="$newCompany" maxWidth="2xl" focusable>
        <form method="POST" action="{{ route('companies.store') }}" novalidate x-data="{ loading: false }" @submit="loading = true">
            @csrf
            <input type="hidden" name="_new_company" value="1">
            <div class="flex items-start gap-4 border-b border-slate-100 px-6 py-5">
                <div class="min-w-0 flex-1">
                    <h2 class="text-lg font-bold text-brand-800">Add a company</h2>
                    <p class="mt-0.5 text-sm text-slate-500">Add the company's first admin too (recommended). New accounts start with the default password <code class="rounded bg-slate-100 px-1.5 py-0.5 text-slate-700">{{ \App\Http\Controllers\UserController::DEFAULT_PASSWORD }}</code>.</p>
                </div>
                <button type="button" x-on:click="$dispatch('close')" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600" aria-label="Close">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>
                </button>
            </div>

            <div class="space-y-5 px-6 py-5">
                @if ($newCompany)
                    <div role="alert" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">Nothing was saved. Please fix the highlighted fields below.</div>
                @endif

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label for="c-name" class="mb-1 block text-sm font-medium text-slate-700">Company name</label>
                        <input id="c-name" name="name" value="{{ old('name') }}" required placeholder="Partner Company Inc." class="w-full rounded-lg {{ $newCompany && $errors->has('name') ? $bad : $ok }}">
                        @if ($newCompany) @error('name') <p role="alert" class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror @endif
                    </div>
                    <div>
                        <label for="c-phone" class="mb-1 block text-sm font-medium text-slate-700">Phone <span class="font-normal text-slate-400">(optional)</span></label>
                        <input id="c-phone" name="phone" value="{{ old('phone') }}" class="w-full rounded-lg {{ $ok }}">
                    </div>
                    <div>
                        <label for="c-address" class="mb-1 block text-sm font-medium text-slate-700">Address <span class="font-normal text-slate-400">(optional)</span></label>
                        <input id="c-address" name="address" value="{{ old('address') }}" class="w-full rounded-lg {{ $ok }}">
                    </div>
                </div>

                <div class="border-t border-slate-100 pt-4">
                    <p class="mb-3 text-sm font-semibold text-slate-800">First admin <span class="font-normal text-slate-400">(leave empty to add later)</span></p>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="a-name" class="mb-1 block text-sm font-medium text-slate-700">Full name</label>
                            <input id="a-name" name="admin_name" value="{{ old('admin_name') }}" class="w-full rounded-lg {{ $newCompany && $errors->has('admin_name') ? $bad : $ok }}">
                            @if ($newCompany) @error('admin_name') <p role="alert" class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror @endif
                        </div>
                        <div>
                            <label for="a-email" class="mb-1 block text-sm font-medium text-slate-700">Email</label>
                            <input id="a-email" name="admin_email" type="email" value="{{ old('admin_email') }}" autocapitalize="none" class="w-full rounded-lg {{ $newCompany && $errors->has('admin_email') ? $bad : $ok }}">
                            @if ($newCompany) @error('admin_email') <p role="alert" class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror @endif
                        </div>
                        <div>
                            <label for="a-username" class="mb-1 block text-sm font-medium text-slate-700">Username</label>
                            <input id="a-username" name="admin_username" value="{{ old('admin_username') }}" autocapitalize="none" spellcheck="false" class="w-full rounded-lg {{ $newCompany && $errors->has('admin_username') ? $bad : $ok }}">
                            @if ($newCompany) @error('admin_username') <p role="alert" class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-2 border-t border-slate-100 bg-slate-50/60 px-6 py-4">
                <button type="button" x-on:click="$dispatch('close')" class="rounded-lg bg-white px-4 py-2 text-sm font-medium text-slate-700 ring-1 ring-slate-300 hover:bg-slate-50">Cancel</button>
                <button type="submit" :disabled="loading" class="rounded-lg bg-brand-800 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700 disabled:opacity-60">Create company</button>
            </div>
        </form>
    </x-modal>
</x-app-layout>
