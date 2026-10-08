<x-app-layout>
    @php
        $role       = auth()->user()->role;
        $isPartner  = $role === 'user';
        $isStaff    = auth()->user()->isStaff();
        $isAdmin    = in_array($role, ['admin', 'super_admin']);
        $isEngineer = $role === 'it_support';
        $canLog     = auth()->user()->canLogTickets();
        $tabs = ['' => 'All'];
        if ($isAdmin) $tabs['unassigned'] = 'Needs assignment';
        if (! $isEngineer) $tabs['open'] = 'Open';
        $tabs += ['assigned' => 'Assigned', 'in_progress' => 'In progress', 'on_hold' => 'On hold', 'resolved' => 'Resolved', 'closed' => 'Closed', 'cancelled' => 'Cancelled'];
        $current   = request('status', '');
        $newUrl    = $isPartner ? route('dashboard') : route('tickets.create');
        $hasFilter = $current !== '' || request()->filled('search');
        $accent    = ['low' => 'bg-slate-300', 'medium' => 'bg-sky-400', 'high' => 'bg-orange-500', 'critical' => 'bg-red-600'];
        $sortOptions = [
            ['value' => 'created_at|desc', 'label' => 'Newest first'],
            ['value' => 'created_at|asc',  'label' => 'Oldest first'],
            ['value' => 'priority|desc',   'label' => 'Priority: high to low'],
            ['value' => 'priority|asc',    'label' => 'Priority: low to high'],
            ['value' => 'status|asc',      'label' => 'Status'],
            ['value' => 'subject|asc',     'label' => 'Subject A-Z'],
        ];
    @endphp

    <x-slot name="header">{{ $isPartner ? 'My Tickets' : ($isEngineer ? 'My Assignments' : 'All Tickets') }}</x-slot>

    @if ($isAdmin)
        {{-- Appears when someone submits a ticket while this list is open. --}}
        <div x-data x-show="$store.pending.newSinceLoad > 0" x-cloak role="status" aria-live="polite"
             class="mb-4 flex flex-col gap-2 rounded-xl border border-violet-200 bg-violet-50 px-4 py-3 text-sm text-violet-900 sm:flex-row sm:items-center sm:justify-between">
            <span class="flex items-center gap-2">
                <span class="relative flex h-2 w-2"><span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-violet-400 opacity-75"></span><span class="relative inline-flex h-2 w-2 rounded-full bg-violet-600"></span></span>
                <span><strong x-text="$store.pending.newSinceLoad"></strong>
                    <span x-text="$store.pending.newSinceLoad === 1 ? 'new ticket is' : 'new tickets are'"></span> waiting for acceptance.</span>
            </span>
            <a href="{{ request()->fullUrl() }}" class="shrink-0 font-semibold text-violet-700 hover:underline">Refresh list</a>
        </div>
    @endif

    {{-- Toolbar --}}
    <div class="mb-4 space-y-3">
        <form method="GET" class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-end">
            @foreach (['status', 'sort', 'dir', 'per_page'] as $keep)
                @if (request()->filled($keep)) <input type="hidden" name="{{ $keep }}" value="{{ request($keep) }}"> @endif
            @endforeach
            <div class="relative flex-1 sm:max-w-sm">
                <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m20 20-3.5-3.5"/></svg>
                <input name="search" value="{{ request('search') }}" placeholder="{{ $isStaff ? 'Search subject, ticket no. or requester' : 'Search subject or ticket no.' }}"
                       class="w-full rounded-lg border-slate-300 py-2 pl-9 text-sm focus:border-brand-500 focus:ring-brand-500">
            </div>
            <div class="flex gap-2">
                <button class="rounded-lg bg-brand-800 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">Search</button>
                @if ($hasFilter)
                    <a href="{{ route('tickets.index') }}" class="rounded-lg bg-white px-3 py-2 text-sm font-medium text-slate-600 ring-1 ring-slate-300 hover:bg-slate-50">Clear</a>
                @endif
                @if ($canLog)
                    <a href="{{ $newUrl }}" class="inline-flex shrink-0 items-center gap-1.5 rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white hover:bg-brand-600">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg> New
                    </a>
                @endif
            </div>
        </form>

        <div class="-mx-1 flex gap-1.5 overflow-x-auto px-1 pb-1">
            @foreach ($tabs as $value => $label)
                @php($n = $value === '' ? $counts->sum() : ($value === 'unassigned' ? $needsAssignment : ($counts[$value] ?? 0)))
                <a href="{{ request()->fullUrlWithQuery(['status' => $value ?: null, 'page' => null]) }}"
                   class="flex shrink-0 items-center gap-2 rounded-full px-3.5 py-1.5 text-sm font-medium transition {{ $current === $value ? 'bg-brand-800 text-white shadow-sm' : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50' }}">
                    {{ $label }}
                    @if ($value === 'unassigned')
                        <span x-data x-text="$store.pending.count" class="rounded-full px-1.5 text-xs transition-colors"
                              :class="{{ $current === $value ? 'true' : 'false' }} ? 'bg-white/20' : ($store.pending.count > 0 ? 'bg-violet-100 font-semibold text-violet-700' : 'bg-slate-100 text-slate-500')">{{ $n }}</span>
                    @else
                        <span class="rounded-full px-1.5 text-xs {{ $current === $value ? 'bg-white/20' : 'bg-slate-100 text-slate-500' }}">{{ $n }}</span>
                    @endif
                </a>
            @endforeach
        </div>
    </div>

    @if ($tickets->isEmpty())
        <div class="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-16 text-center">
            <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-brand-50 text-brand-600">
                <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6M9 16h6M7 3h7l5 5v13H7z"/></svg>
            </div>
            @if ($hasFilter)
                <p class="font-semibold text-slate-800">No tickets match your filter</p>
                <p class="mt-1 text-sm text-slate-500">Try a different status or search term.</p>
                <a href="{{ route('tickets.index') }}" class="mt-5 inline-block rounded-lg bg-white px-4 py-2 text-sm font-medium text-slate-700 ring-1 ring-slate-300 hover:bg-slate-50">Clear filters</a>
            @else
                <p class="font-semibold text-slate-800">No tickets yet</p>
                <p class="mt-1 text-sm text-slate-500">{{ $isPartner ? 'Tickets you submit will appear here.' : ($isEngineer ? 'Tickets an admin assigns to you will appear here.' : 'Tickets submitted by partners will appear here.') }}</p>
                @if ($isPartner)
                    <a href="{{ $newUrl }}" class="mt-5 inline-block rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white hover:bg-brand-600">Submit a ticket</a>
                @endif
            @endif
        </div>
    @else
        {{-- Desktop table: toolbar, rows and pager live in one card --}}
        <div class="hidden overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm md:block">
            <x-table-controls :paginator="$tickets" :options="$sortOptions" default="created_at|desc" noun="ticket" embedded />
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="border-b border-slate-200 bg-slate-50/80 text-xs font-semibold text-slate-600">
                        <tr>
                            <x-th-sort key="subject" class="py-2.5 pl-5 pr-3">Ticket</x-th-sort>
                            @if ($isStaff) <th scope="col" class="px-3 py-2.5 text-left">Requester</th> @endif
                            <x-th-sort key="priority">Priority</x-th-sort>
                            <x-th-sort key="status">Status</x-th-sort>
                            <th scope="col" class="hidden px-3 py-2.5 text-left lg:table-cell">Engineer</th>
                            <x-th-sort key="created_at" :default="true" default-dir="desc" class="hidden px-3 py-2.5 text-left xl:table-cell">Created</x-th-sort>
                            <th scope="col" class="py-2.5 pl-3 pr-5 text-right"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($tickets as $t)
                            @php($sla = $isStaff ? $t->slaBadge() : null)
                            <tr x-data @click="if (! $event.target.closest('a, button, form, select')) window.location = '{{ route('tickets.show', $t) }}'"
                                class="group cursor-pointer transition hover:bg-brand-50/40 {{ $isStaff && $t->isOverdue() ? 'bg-red-50/40' : '' }}">
                                <td class="relative max-w-sm py-3 pl-5 pr-3">
                                    <span class="absolute inset-y-2.5 left-0 w-1 rounded-r-full {{ $t->isFinished() ? 'bg-slate-200' : ($accent[$t->priority] ?? 'bg-slate-300') }}" aria-hidden="true"></span>
                                    <a href="{{ route('tickets.show', $t) }}" class="line-clamp-1 font-semibold text-slate-900 group-hover:text-brand-700">{{ $t->subject }}</a>
                                    <p class="mt-0.5 font-mono text-[11px] text-slate-400">{{ $t->ticket_no }}</p>
                                </td>
                                @if ($isStaff)
                                    <td class="px-3 py-3">
                                        <div class="flex items-center gap-2.5">
                                            <x-avatar :user="$t->user" size="h-8 w-8" text="text-xs" />
                                            <div class="min-w-0">
                                                <p class="line-clamp-1 font-medium text-slate-800">{{ $t->user->name }}</p>
                                                @if ($t->user->company) <p class="line-clamp-1 text-xs text-slate-500">{{ $t->user->company }}</p> @endif
                                            </div>
                                        </div>
                                    </td>
                                @endif
                                <td class="px-3 py-3"><x-ticket-pill :ticket="$t" type="priority" /></td>
                                <td class="px-3 py-3">
                                    <x-ticket-pill :ticket="$t" />
                                    @if ($sla) <p class="mt-1.5 text-xs {{ $sla[1] }}">{{ $sla[0] }}</p> @endif
                                </td>
                                <td class="hidden px-3 py-3 lg:table-cell">
                                    @if ($t->assignee)
                                        <span class="flex items-center gap-2 text-slate-800"><x-avatar :user="$t->assignee" size="h-7 w-7" text="text-[10px]" /><span class="line-clamp-1">{{ $t->assignee->name }}</span></span>
                                    @else
                                        <span class="rounded-full border border-dashed border-slate-300 px-2.5 py-0.5 text-xs text-slate-400">Unassigned</span>
                                    @endif
                                </td>
                                <td class="hidden whitespace-nowrap px-3 py-3 xl:table-cell">
                                    <p class="text-slate-700" title="{{ $t->created_at->format('M d, Y h:i A') }}">{{ $t->created_at->diffForHumans() }}</p>
                                </td>
                                <td class="py-3 pl-3 pr-5">
                                    <div class="flex items-center justify-end gap-2">
                                        @if ($isAdmin && $t->status === 'open' && ! $t->assigned_to)
                                            <a href="{{ route('tickets.show', $t) }}#assign" class="whitespace-nowrap rounded-lg bg-violet-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-violet-700">Accept &amp; assign</a>
                                        @endif
                                        @if ($isEngineer && $t->support_type === 'onsite' && $t->directionsUrl() && ! $t->isFinished())
                                            <a href="{{ $t->directionsUrl() }}" target="_blank" rel="noopener" class="rounded-lg px-3 py-1.5 text-xs font-medium text-slate-700 ring-1 ring-slate-300 hover:bg-white">Map</a>
                                        @endif
                                        @if ($isPartner && $t->isCancellable())
                                            <button type="button" x-on:click="$dispatch('open-modal', 'cancel-{{ $t->id }}')" class="rounded-lg px-3 py-1.5 text-xs font-medium text-rose-700 ring-1 ring-rose-200 hover:bg-rose-50">Cancel</button>
                                        @endif
                                        <a href="{{ route('tickets.show', $t) }}" class="inline-flex items-center gap-1 rounded-lg px-3 py-1.5 text-xs font-medium text-brand-700 ring-1 ring-brand-200 transition group-hover:bg-brand-800 group-hover:text-white group-hover:ring-brand-800">
                                            View
                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m9 6 6 6-6 6"/></svg>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($tickets->hasPages())
                <div class="border-t border-slate-200 bg-slate-50/70 px-5 py-3">{{ $tickets->links() }}</div>
            @endif
        </div>

        {{-- Mobile cards --}}
        <div class="md:hidden">
            <x-table-controls :paginator="$tickets" :options="$sortOptions" default="created_at|desc" noun="ticket" />
            <div class="space-y-3">
            @foreach ($tickets as $t)
                <div class="relative overflow-hidden rounded-xl border border-slate-200 bg-white p-4 pl-5 shadow-sm">
                    <span class="absolute inset-y-0 left-0 w-1 {{ $t->isFinished() ? 'bg-slate-200' : ($accent[$t->priority] ?? 'bg-slate-300') }}" aria-hidden="true"></span>
                    <div class="flex items-start justify-between gap-3">
                        <a href="{{ route('tickets.show', $t) }}" class="font-semibold text-slate-900 hover:text-brand-700">{{ $t->subject }}</a>
                        <x-ticket-pill :ticket="$t" class="shrink-0" />
                    </div>
                    <p class="mt-0.5 font-mono text-xs text-slate-400">{{ $t->ticket_no }}</p>
                    <div class="mt-3 flex flex-wrap items-center gap-2 text-xs text-slate-500">
                        <x-ticket-pill :ticket="$t" type="priority" />
                        @if ($isStaff) <span>{{ $t->user->name }}</span> @endif
                        <span>&middot; {{ $t->assignee?->name ?? 'Unassigned' }}</span>
                        <span>&middot; {{ $t->created_at->diffForHumans() }}</span>
                    </div>
                    <div class="mt-3 flex gap-2 border-t border-slate-100 pt-3">
                        <a href="{{ route('tickets.show', $t) }}" class="flex-1 rounded-lg py-2 text-center text-xs font-medium text-brand-700 ring-1 ring-brand-200 hover:bg-brand-50">View</a>
                        @if ($isAdmin && $t->status === 'open' && ! $t->assigned_to)
                            <a href="{{ route('tickets.show', $t) }}#assign" class="flex-1 rounded-lg bg-violet-600 py-2 text-center text-xs font-semibold text-white">Accept &amp; assign</a>
                        @endif
                        @if ($isPartner && $t->isCancellable())
                            <button type="button" x-data x-on:click="$dispatch('open-modal', 'cancel-{{ $t->id }}')"
                                    class="flex-1 rounded-lg py-2 text-xs font-medium text-rose-700 ring-1 ring-rose-200 hover:bg-rose-50">Cancel</button>
                        @endif
                    </div>
                </div>
            @endforeach
            @if ($tickets->hasPages()) <div class="pt-2">{{ $tickets->links() }}</div> @endif
            </div>
        </div>

        @if ($isPartner)
            @foreach ($tickets->getCollection()->filter->isCancellable() as $t)
                @include('tickets._cancel-modal', ['ticket' => $t])
            @endforeach
        @endif
    @endif
</x-app-layout>
