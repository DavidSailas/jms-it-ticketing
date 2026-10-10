<x-app-layout>
    <x-slot name="header">Company</x-slot>

    @php
        $ok  = 'border-slate-300 focus:border-brand-500 focus:ring-brand-500';
        $bad = 'border-red-400 focus:border-red-500 focus:ring-red-500';
        $badge = [
            'user'       => 'bg-slate-100 text-slate-700 ring-slate-200',
            'it_support' => 'bg-sky-50 text-sky-700 ring-sky-200',
            'admin'      => 'bg-violet-50 text-violet-700 ring-violet-200',
            'super_admin' => 'bg-amber-50 text-amber-800 ring-amber-300',
        ];
        $tabs = ['' => 'Everyone', 'admin' => 'Admins', 'it_support' => 'IT Support', 'user' => 'Users'];
        if ($isJms) unset($tabs['user']); // JMS One IT is our own team: no partner users here
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
                <h2 class="flex items-center gap-2 truncate text-xl font-extrabold tracking-tight text-brand-800">{{ $company->name }}
                    @if ($isJms) <span class="rounded-full bg-amber-50 px-2.5 py-0.5 text-[11px] font-bold uppercase tracking-wider text-amber-800 ring-1 ring-inset ring-amber-300">Our company</span> @endif
                </h2>
                <p class="text-sm text-slate-500">{{ $all }} {{ $isJms ? \Illuminate\Support\Str::plural('team member', $all) : \Illuminate\Support\Str::plural('member', $all) }}@if ($company->phone) &middot; {{ $company->phone }}@endif @if ($company->address) &middot; {{ $company->address }}@endif</p>
            </div>
        </div>
        <button type="button" x-data x-on:click="$dispatch('open-modal', 'add-member')"
                class="inline-flex shrink-0 items-center gap-1.5 rounded-lg bg-brand-800 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
            Add member
        </button>
    </div>

    @if ($isJms)
        {{-- Our own company: the JMS team and its workload (every partner's tickets are in All Tickets) --}}
        <div class="mb-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
            @foreach ([['Team members', $all, 'text-slate-900'], ['IT engineers', $counts['it_support'] ?? 0, 'text-sky-600'], ['Active tickets', $team['active'], 'text-amber-600'], ['Resolved this month', $team['resolved'], 'text-emerald-600']] as [$label, $value, $color])
                <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
                    <p class="text-2xl font-extrabold {{ $color }}">{{ $value }}</p>
                    <p class="text-xs text-slate-500">{{ $label }}</p>
                </div>
            @endforeach
        </div>
    @else
    {{-- Ticket summary: each tile also filters the ticket cards below --}}
    <div class="mb-5 grid grid-cols-3 gap-3">
        @foreach ([['all', 'Tickets', $tickets['total'], 'text-slate-900'], ['active', 'Active now', $tickets['active'], 'text-amber-600'], ['done', 'Resolved', $tickets['done'], 'text-emerald-600']] as [$key, $label, $value, $color])
            @php($on = $view === $key)
            <a href="{{ request()->fullUrlWithQuery(['tickets' => $key === 'all' ? null : $key, 'tp' => null]) }}#tickets" @if ($on) aria-current="true" @endif
               class="rounded-xl border bg-white px-4 py-3 shadow-sm transition hover:border-brand-300 hover:shadow {{ $on ? 'border-brand-500 ring-2 ring-brand-100' : 'border-slate-200' }}">
                <p class="text-2xl font-extrabold {{ $color }}">{{ $value }}</p>
                <p class="text-xs text-slate-500">{{ $label }}</p>
            </a>
        @endforeach
    </div>

    {{-- Tickets: List / Board switch, same as All Tickets --}}
    <section id="tickets" class="mb-5 scroll-mt-20" aria-labelledby="company-tickets-heading"
             x-data="{ open: false, t: {}, engineer: '', support: '', priority: 'medium', picker: '', loading: false, failed: false,
                       openAssign(t) { this.t = t; this.engineer = t.assigned_to ? String(t.assigned_to) : ''; this.support = t.support_type || ''; this.priority = t.priority || 'medium'; this.open = true; this.loadPicker(); },
                       async loadPicker() { this.picker = ''; this.failed = false; this.loading = true; try { const r = await fetch('{{ route('tickets.engineer-picker') }}', { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' }); if (!r.ok) throw new Error(); this.picker = await r.text(); } catch (e) { this.failed = true; } this.loading = false; } }"
             @keydown.escape.window="open = false">
        <div class="mb-3 flex flex-wrap items-end justify-between gap-3">
            <div>
                <h3 id="company-tickets-heading" class="font-semibold text-brand-800">{{ $ticketHeading }}</h3>
                <p class="text-xs text-slate-500">
                    {{ $ticketCount }} {{ \Illuminate\Support\Str::plural('ticket', $ticketCount) }}, newest first. Click a ticket to open it.
                    @if ($view !== 'all') <a href="{{ request()->fullUrlWithQuery(['tickets' => null, 'tp' => null]) }}#tickets" class="font-semibold text-brand-600 hover:underline">Show all</a> @endif
                </p>
            </div>
            <div class="inline-flex rounded-lg bg-white p-1 text-sm font-medium ring-1 ring-slate-200" role="group" aria-label="Ticket view">
                @foreach (['list' => 'List', 'board' => 'Board'] as $key => $label)
                    @if ($style === $key)
                        <span class="rounded-md bg-brand-800 px-3 py-1 text-white" aria-current="page">{{ $label }}</span>
                    @else
                        <a href="{{ request()->fullUrlWithQuery(['tv' => $key === 'list' ? null : $key, 'tp' => null]) }}#tickets" class="rounded-md px-3 py-1 text-slate-600 hover:bg-slate-50">{{ $label }}</a>
                    @endif
                @endforeach
            </div>
        </div>

        @if ($errors->has('assigned_to') || $errors->has('support_type') || $errors->has('priority'))
            <div role="alert" class="mb-3 rounded-lg border border-red-200 bg-red-50 px-4 py-2.5 text-sm text-red-700">
                {{ $errors->first('assigned_to') ?: ($errors->first('support_type') ?: $errors->first('priority')) }}
            </div>
        @endif

        @if ($style === 'list')
            @if ($ticketList->isEmpty())
                <div class="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-10 text-center">
                    <p class="font-semibold text-slate-800">{{ $view === 'all' ? 'No tickets yet' : 'No tickets here' }}</p>
                    <p class="mt-1 text-sm text-slate-500">{{ $view === 'all' ? 'Tickets from this company will appear here.' : 'Try another filter above.' }}</p>
                </div>
            @else
                <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-left text-sm">
                            <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th scope="col" class="px-4 py-3">Ticket</th>
                                    <th scope="col" class="px-4 py-3">Requester</th>
                                    <th scope="col" class="px-4 py-3">Status</th>
                                    <th scope="col" class="px-4 py-3">Priority</th>
                                    <th scope="col" class="px-4 py-3">Engineer</th>
                                    <th scope="col" class="px-4 py-3">Created</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($ticketList as $t)
                                    <tr class="transition hover:bg-brand-50/40">
                                        <td class="max-w-xs px-4 py-3">
                                            <a href="{{ route('tickets.show', $t) }}" class="block truncate font-medium text-slate-900 hover:text-brand-700">{{ $t->subject }}</a>
                                            <span class="text-xs text-slate-400">{{ $t->ticket_no }} &middot; {{ $t->category }}</span> <x-jms-requested-badge :ticket="$t" />
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="flex items-center gap-1.5 text-slate-700"><span class="truncate">{{ $t->user->name }}</span><x-admin-badge :user="$t->user" /></span>
                                        </td>
                                        <td class="px-4 py-3"><x-ticket-pill :ticket="$t" /></td>
                                        <td class="px-4 py-3"><x-ticket-pill :ticket="$t" type="priority" /></td>
                                        <td class="px-4 py-3">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span class="{{ $t->assignee ? 'text-slate-600' : 'text-slate-400' }}">{{ $t->assignee?->name ?? 'Unassigned' }}</span>
                                                @if ($canAssign && ! $t->isFinished())
                                                    <button type="button" @click="openAssign(@js(\App\Support\AssignPayload::for($t)))"
                                                            class="rounded-md px-2 py-1 text-xs font-semibold focus:outline-none focus-visible:ring-2 focus-visible:ring-violet-500 {{ $t->assignee ? 'text-violet-700 hover:bg-violet-50' : 'bg-violet-600 text-white hover:bg-violet-700' }}">
                                                        {{ $t->assignee ? 'Reassign' : 'Accept & assign' }}
                                                    </button>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-3 text-xs text-slate-500" title="{{ $t->created_at->format('M d, Y h:i A') }}">{{ $t->created_at->diffForHumans() }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                @if ($ticketList->hasPages()) <div class="mt-4">{{ $ticketList->links() }}</div> @endif
            @endif
        @else
            <div class="-mx-4 flex gap-4 overflow-x-auto px-4 pb-3 sm:mx-0 sm:px-0">
                @foreach ($board as $status => $col)
                    <div class="flex w-72 shrink-0 flex-col rounded-2xl border border-slate-200 bg-slate-50" aria-label="{{ $col['label'] }} column">
                        <div class="flex items-center justify-between px-4 py-3">
                            <h4 class="text-sm font-semibold text-slate-700">{{ $col['label'] }}</h4>
                            <span class="rounded-full bg-white px-2 py-0.5 text-xs font-semibold text-slate-500 ring-1 ring-slate-200">{{ $col['total'] }}</span>
                        </div>
                        <div class="flex flex-1 flex-col gap-3 px-3 pb-3">
                            @forelse ($col['tickets'] as $t)
                                <x-companies.ticket-card :ticket="$t" :assignable="$canAssign" />
                            @empty
                                <p class="py-4 text-center text-xs text-slate-400">No tickets</p>
                            @endforelse
                            @if ($col['total'] > $col['tickets']->count())
                                <p class="text-center text-[11px] text-slate-400">Showing the newest {{ $col['tickets']->count() }}. See the List view for all.</p>
                            @endif
                            @if ($status === 'resolved') <p class="text-center text-[11px] text-slate-400">Resolved in the last 14 days. Closed tickets are in the List view.</p> @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        @if ($canAssign)
            <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-end justify-center sm:items-center sm:p-6" role="dialog" aria-modal="true" aria-labelledby="assign-title">
                <div x-show="open" x-transition.opacity class="absolute inset-0 bg-slate-900/50" @click="open = false"></div>
                <form method="POST" :action="t.action" x-show="open" x-transition
                      class="relative flex max-h-[92vh] w-full flex-col overflow-hidden rounded-t-2xl bg-white shadow-xl sm:max-w-2xl sm:rounded-2xl">
                    @csrf
                    <div class="flex items-start justify-between gap-4 border-b border-slate-200 px-5 py-4">
                        <div class="min-w-0">
                            <h3 id="assign-title" class="font-semibold text-brand-800" x-text="t.assigned_to ? 'Reassign ticket' : 'Accept & assign'"></h3>
                            <p class="mt-0.5 truncate text-sm text-slate-600"><span class="font-medium text-brand-700" x-text="t.no"></span> &middot; <span x-text="t.subject"></span></p>
                            <p class="text-xs text-slate-400">Requested by <span x-text="t.requester"></span> &middot; {{ $company->name }}</p>
                        </div>
                        <button type="button" @click="open = false" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600" aria-label="Close">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6 6 18"/></svg>
                        </button>
                    </div>

                    <div class="space-y-5 overflow-y-auto px-5 py-4">
                        <div>
                            <span class="mb-2 block text-sm font-medium text-slate-700">IT engineer</span>
                            <div x-ref="picker" x-html="picker" aria-live="polite"></div>
                            <p x-show="loading" class="rounded-lg bg-slate-50 px-3 py-6 text-center text-sm text-slate-500">Loading engineers...</p>
                            <p x-show="failed" x-cloak class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">Could not load the engineers. Close this panel and try again, or <a :href="t.url + '#assign'" class="font-semibold underline">open the full ticket</a>.</p>
                            <p class="mt-2 text-xs text-slate-500">Only JMS support engineers can be assigned from here.</p>
                        </div>

                        <div>
                            <span class="mb-1 block text-sm font-medium text-slate-700">Support type</span>
                            <div class="grid grid-cols-2 gap-2">
                                @foreach (\App\Models\Ticket::SUPPORT_TYPES as $k => $label)
                                    <label class="cursor-pointer">
                                        <input type="radio" name="support_type" value="{{ $k }}" x-model="support" class="peer sr-only" required>
                                        <span class="flex items-center justify-center rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-600 transition peer-checked:border-brand-600 peer-checked:bg-brand-50 peer-checked:text-brand-700">{{ $label }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <div>
                            <label for="assign-priority" class="mb-1 block text-sm font-medium text-slate-700">Priority</label>
                            <select id="assign-priority" name="priority" x-model="priority" class="w-full rounded-lg border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500">
                                @foreach (\App\Models\Ticket::PRIORITIES as $p)
                                    <option value="{{ $p }}">{{ ucfirst($p) }} (target {{ \App\Models\Ticket::SLA_HOURS[$p] }}h)</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="assign-note" class="mb-1 block text-sm font-medium text-slate-700">Note for the engineer <span class="font-normal text-slate-400">(optional)</span></label>
                            <textarea id="assign-note" name="note" rows="2" maxlength="500" class="w-full rounded-lg border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500" placeholder="e.g. Bring a spare router. Ask for Ms. Reyes at reception."></textarea>
                        </div>
                    </div>

                    <div class="flex flex-col-reverse gap-2 border-t border-slate-200 bg-slate-50 px-5 py-3 sm:flex-row sm:items-center sm:justify-between">
                        <a :href="t.url" class="text-center text-sm font-medium text-slate-500 hover:text-brand-700">Open full ticket</a>
                        <div class="flex gap-2">
                            <button type="button" @click="open = false" class="flex-1 rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50 sm:flex-none">Cancel</button>
                            <button class="flex-1 rounded-lg bg-violet-600 px-4 py-2 text-sm font-semibold text-white hover:bg-violet-700 sm:flex-none" x-text="t.assigned_to ? 'Save assignment' : 'Accept & assign'"></button>
                        </div>
                    </div>
                </form>
            </div>
        @endif
    </section>

    @endif

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
                    <p class="mt-1 text-sm text-slate-500">{{ request()->hasAny(['search', 'role']) ? 'Try another tab or search.' : ($isJms ? 'Add your first JMS admin or IT engineer.' : 'Add the company admin first; they can add the rest.') }}</p>
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
                                @if ($isJms)
                                    @if ($m->role === 'it_support') <span class="hidden shrink-0 text-xs text-slate-400 sm:block">{{ $m->active_count }} active</span> @endif
                                @else
                                    <span class="hidden shrink-0 text-xs text-slate-400 sm:block">{{ $m->tickets_count }} {{ \Illuminate\Support\Str::plural('ticket', $m->tickets_count) }}</span>
                                @endif
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

            @unless ($isJms)
            <form method="POST" action="{{ route('companies.destroy', $company) }}" onsubmit="return confirm('Delete {{ addslashes($company->name) }}? This cannot be undone.')"
                  class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                @csrf @method('DELETE')
                <h3 class="font-semibold text-slate-800">Delete company</h3>
                <p class="mt-1 text-sm text-slate-500">Only possible when the company has no people and no tickets.</p>
                <button type="submit" @disabled($all > 0 || $tickets['total'] > 0) class="mt-3 rounded-lg px-4 py-2 text-sm font-semibold text-rose-700 ring-1 ring-rose-200 hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:bg-transparent">Delete company</button>
            </form>
            @endunless
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
                            @foreach ($isJms ? ['admin' => 'Admin', 'it_support' => 'IT Support'] : ['admin' => 'Admin', 'it_support' => 'IT Support', 'user' => 'User'] as $v => $l)
                                <option value="{{ $v }}" @selected(old('role', $isJms ? 'it_support' : 'user') === $v)>{{ $l }}</option>
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
