<x-app-layout>
    <x-slot name="header">Schedule</x-slot>

    @php
        $prev    = $month->copy()->subMonth()->format('Y-m');
        $next    = $month->copy()->addMonth()->format('Y-m');
        $inMonth = $today->isSameMonth($month);
        $weekdays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        $days    = collect(\Carbon\CarbonPeriod::create($gridStart, $gridEnd));
        $hasFilter = $filters['engineer'] !== '' || $filters['type'] !== '' || $filters['status'] !== '';

        // Event colours follow the status colours used across the app.
        $chip = [
            'open'        => 'bg-blue-50 text-blue-800 ring-blue-200 border-l-blue-500',
            'assigned'    => 'bg-indigo-50 text-indigo-800 ring-indigo-200 border-l-indigo-500',
            'in_progress' => 'bg-amber-50 text-amber-800 ring-amber-200 border-l-amber-500',
            'on_hold'     => 'bg-slate-100 text-slate-700 ring-slate-200 border-l-slate-400',
            'resolved'    => 'bg-emerald-50 text-emerald-800 ring-emerald-200 border-l-emerald-500',
            'closed'      => 'bg-slate-100 text-slate-500 ring-slate-200 border-l-slate-400',
            'cancelled'   => 'bg-rose-50 text-rose-700 ring-rose-200 border-l-rose-500',
        ];
        $dot = [
            'open' => 'bg-blue-500', 'assigned' => 'bg-indigo-500', 'in_progress' => 'bg-amber-500', 'on_hold' => 'bg-slate-400',
            'resolved' => 'bg-emerald-500', 'closed' => 'bg-slate-400', 'cancelled' => 'bg-rose-500',
        ];
        $cards = [
            ['Today', $stats['today'], 'text-brand-800', 'Active bookings today'],
            ['Next 7 days', $stats['week'], 'text-indigo-600', 'Coming up this week'],
            [$month->format('F') . ' bookings', $stats['month'], 'text-slate-800', 'Excluding cancelled'],
            [$isAdmin ? 'Needs an engineer' : 'Past due', $stats['attention'], $stats['attention'] > 0 ? ($isAdmin ? 'text-violet-600' : 'text-red-600') : 'text-slate-400', $isAdmin ? 'Booked, not yet assigned' : 'Booked time has passed'],
        ];
    @endphp

    <div class="mb-6 flex flex-col gap-1">
        <h2 class="text-2xl font-extrabold tracking-tight text-brand-800">Schedule</h2>
        <p class="text-sm text-slate-500">
            {{ $isAdmin ? 'Every booked visit and call, across the whole team.' : 'The visits and calls booked for you.' }}
        </p>
    </div>

    {{-- At-a-glance numbers --}}
    <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
        @foreach ($cards as [$label, $value, $color, $hint])
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ $label }}</p>
                <p class="mt-2 text-3xl font-extrabold {{ $color }}">{{ $value }}</p>
                <p class="mt-1 text-xs text-slate-400">{{ $hint }}</p>
            </div>
        @endforeach
    </div>

    <div x-data="{ selected: @js($selected->toDateString()) }" class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">

        {{-- Calendar --}}
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" aria-label="Booking calendar">
            <div class="flex flex-col gap-3 border-b border-slate-100 p-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-2">
                    <a href="{{ request()->fullUrlWithQuery(['month' => $prev, 'date' => null]) }}" aria-label="Previous month"
                       class="rounded-lg p-2 text-slate-500 ring-1 ring-slate-200 transition hover:bg-slate-50 hover:text-slate-800">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m15 5-7 7 7 7"/></svg>
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['month' => $next, 'date' => null]) }}" aria-label="Next month"
                       class="rounded-lg p-2 text-slate-500 ring-1 ring-slate-200 transition hover:bg-slate-50 hover:text-slate-800">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m9 5 7 7-7 7"/></svg>
                    </a>
                    <h3 class="ml-1 text-lg font-bold text-brand-800">{{ $month->format('F Y') }}</h3>
                </div>
                <a href="{{ request()->fullUrlWithQuery(['month' => null, 'date' => null]) }}"
                   class="self-start rounded-lg px-3.5 py-2 text-sm font-medium text-brand-700 ring-1 ring-brand-200 transition hover:bg-brand-50 sm:self-auto">Today</a>
            </div>

            {{-- Filters --}}
            <form method="GET" class="flex flex-wrap items-center gap-2 border-b border-slate-100 bg-slate-50/60 px-4 py-3">
                <input type="hidden" name="month" value="{{ $month->format('Y-m') }}">
                @if ($isAdmin)
                    <select name="engineer" onchange="this.form.submit()" aria-label="Filter by engineer"
                            class="rounded-lg border-slate-300 py-1.5 pr-8 text-sm focus:border-brand-500 focus:ring-brand-500">
                        <option value="">All engineers</option>
                        <option value="unassigned" @selected($filters['engineer'] === 'unassigned')>Unassigned</option>
                        @foreach ($engineers as $e)
                            <option value="{{ $e->id }}" @selected($filters['engineer'] === (string) $e->id)>{{ $e->name }}</option>
                        @endforeach
                    </select>
                @endif
                <select name="type" onchange="this.form.submit()" aria-label="Filter by support type"
                        class="rounded-lg border-slate-300 py-1.5 pr-8 text-sm focus:border-brand-500 focus:ring-brand-500">
                    <option value="">Remote &amp; on-site</option>
                    @foreach (\App\Models\Ticket::SUPPORT_TYPES as $key => $label)
                        <option value="{{ $key }}" @selected($filters['type'] === $key)>{{ $label }}</option>
                    @endforeach
                </select>
                <select name="status" onchange="this.form.submit()" aria-label="Filter by status"
                        class="rounded-lg border-slate-300 py-1.5 pr-8 text-sm focus:border-brand-500 focus:ring-brand-500">
                    <option value="">All statuses</option>
                    @foreach (\App\Models\Ticket::STATUSES as $s)
                        <option value="{{ $s }}" @selected($filters['status'] === $s)>{{ ucwords(str_replace('_', ' ', $s)) }}</option>
                    @endforeach
                </select>
                @if ($hasFilter)
                    <a href="{{ route('schedule.index', ['month' => $month->format('Y-m')]) }}" class="text-sm font-medium text-slate-500 hover:text-slate-800">Clear</a>
                @endif
                <noscript><button class="rounded-lg bg-brand-800 px-3 py-1.5 text-sm font-medium text-white">Apply</button></noscript>
            </form>

            {{-- Weekday header --}}
            <div class="grid grid-cols-7 border-b border-slate-100 bg-slate-50 text-center text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                @foreach ($weekdays as $d)
                    <div class="py-2.5">{{ $d }}</div>
                @endforeach
            </div>

            {{-- Day grid --}}
            <div class="grid grid-cols-7">
                @foreach ($days as $day)
                    @php
                        $key     = $day->toDateString();
                        $list    = $events->get($key, collect());
                        $isToday = $day->isSameDay($today);
                        $outside = ! $day->isSameMonth($month);
                    @endphp
                    <button type="button" @click="selected = '{{ $key }}'"
                            :class="selected === '{{ $key }}' ? 'bg-brand-50/70 ring-2 ring-inset ring-brand-500' : '{{ $outside ? 'bg-slate-50/70' : 'bg-white' }} hover:bg-slate-50'"
                            :aria-pressed="selected === '{{ $key }}'"
                            aria-label="{{ $day->format('l, F j') }}, {{ $list->count() }} {{ \Illuminate\Support\Str::plural('booking', $list->count()) }}"
                            class="relative flex min-h-[4.5rem] flex-col items-stretch border-b border-r border-slate-100 p-1.5 text-left transition focus:outline-none focus-visible:z-10 focus-visible:ring-2 focus-visible:ring-brand-500 sm:min-h-[7.5rem] sm:p-2">
                        <span class="flex items-center justify-between">
                            <span class="flex h-6 w-6 items-center justify-center rounded-full text-xs font-semibold sm:h-7 sm:w-7 sm:text-sm
                                {{ $isToday ? 'bg-brand-800 text-white' : ($outside ? 'text-slate-300' : ($day->isWeekend() ? 'text-slate-400' : 'text-slate-700')) }}">{{ $day->day }}</span>
                            @if ($list->isNotEmpty())
                                <span class="hidden rounded-full bg-slate-100 px-1.5 text-[10px] font-semibold text-slate-500 sm:inline">{{ $list->count() }}</span>
                            @endif
                        </span>

                        {{-- Desktop: event chips --}}
                        <span class="mt-1.5 hidden flex-col gap-1 sm:flex">
                            @foreach ($list->take(3) as $t)
                                <span class="block truncate rounded-md border-l-[3px] px-1.5 py-0.5 text-[11px] font-medium ring-1 ring-inset {{ $chip[$t->status] ?? $chip['closed'] }}">
                                    <span class="font-semibold">{{ $t->scheduled_for->format('g:i A') }}</span> {{ $t->subject }}
                                </span>
                            @endforeach
                            @if ($list->count() > 3)
                                <span class="px-1 text-[11px] font-semibold text-brand-700">+{{ $list->count() - 3 }} more</span>
                            @endif
                        </span>

                        {{-- Mobile: dots --}}
                        @if ($list->isNotEmpty())
                            <span class="mt-auto flex flex-wrap gap-0.5 pt-1 sm:hidden">
                                @foreach ($list->take(4) as $t)
                                    <span class="h-1.5 w-1.5 rounded-full {{ $dot[$t->status] ?? 'bg-slate-400' }}"></span>
                                @endforeach
                            </span>
                        @endif
                    </button>
                @endforeach
            </div>

            {{-- Legend --}}
            <div class="flex flex-wrap items-center gap-x-4 gap-y-1.5 border-t border-slate-100 bg-slate-50/60 px-4 py-3 text-xs text-slate-500">
                @foreach (['open' => 'Open', 'assigned' => 'Assigned', 'in_progress' => 'In progress', 'on_hold' => 'On hold', 'resolved' => 'Resolved'] as $k => $label)
                    <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full {{ $dot[$k] }}"></span>{{ $label }}</span>
                @endforeach
            </div>
        </section>

        {{-- Day agenda --}}
        <aside class="rounded-2xl border border-slate-200 bg-white shadow-sm xl:sticky xl:top-24" aria-live="polite">
            @foreach ($days as $day)
                @php
                    $key  = $day->toDateString();
                    $list = $events->get($key, collect());
                @endphp
                <div x-show="selected === '{{ $key }}'" @if ($key !== $selected->toDateString()) x-cloak @endif>
                    <div class="border-b border-slate-100 px-5 py-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                            {{ $day->isSameDay($today) ? 'Today' : ($day->isSameDay($today->copy()->addDay()) ? 'Tomorrow' : $day->format('l')) }}
                        </p>
                        <h3 class="text-lg font-bold text-brand-800">{{ $day->format('F j, Y') }}</h3>
                        <p class="text-sm text-slate-500">{{ $list->count() ? $list->count() . ' ' . \Illuminate\Support\Str::plural('booking', $list->count()) : 'Nothing booked' }}</p>
                    </div>

                    @if ($list->isEmpty())
                        <div class="px-5 py-12 text-center">
                            <svg class="mx-auto h-10 w-10 text-slate-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4.5" width="18" height="16.5" rx="2"/><path d="M3 9.5h18M8 2.5v4M16 2.5v4"/></svg>
                            <p class="mt-3 text-sm font-medium text-slate-700">A clear day</p>
                            <p class="text-sm text-slate-500">No visits or calls are booked for this date.</p>
                        </div>
                    @else
                        <ol class="divide-y divide-slate-100">
                            @foreach ($list as $t)
                                @php($late = $t->scheduled_for->isPast() && in_array($t->status, \App\Models\Ticket::ACTIVE))
                                <li class="group relative flex gap-3 px-5 py-4 transition hover:bg-brand-50/60 focus-within:bg-brand-50/60">
                                    <div class="w-14 shrink-0 pt-0.5 text-right">
                                        <p class="text-sm font-bold text-slate-800">{{ $t->scheduled_for->format('g:i') }}</p>
                                        <p class="text-[11px] font-semibold uppercase text-slate-400">{{ $t->scheduled_for->format('A') }}</p>
                                    </div>
                                    <div class="min-w-0 flex-1 border-l-2 pl-3 {{ $late ? 'border-red-400' : 'border-brand-200' }}">
                                        <div class="mb-1.5 flex flex-wrap items-center gap-1.5">
                                            <x-ticket-pill :ticket="$t" />
                                            <x-ticket-pill :ticket="$t" type="priority" />
                                            @if ($late)
                                                <span class="rounded-full bg-red-50 px-2 py-0.5 text-[11px] font-semibold text-red-700 ring-1 ring-inset ring-red-200">Past due</span>
                                            @endif
                                        </div>
                                        {{-- Stretched link: the whole booking card opens the ticket. --}}
                                        <a href="{{ route('tickets.show', $t) }}"
                                           class="block font-semibold leading-snug text-brand-800 after:absolute after:inset-0 after:content-[''] group-hover:underline focus:outline-none focus-visible:after:ring-2 focus-visible:after:ring-inset focus-visible:after:ring-brand-500">{{ $t->subject }}</a>
                                        <p class="text-xs text-slate-500">{{ $t->ticket_no }} &middot; {{ $t->category }}</p>

                                        <dl class="mt-2 space-y-1 text-sm text-slate-600">
                                            <div class="flex gap-2"><dt class="w-16 shrink-0 text-slate-400">Requester</dt><dd class="min-w-0 truncate">{{ $t->user->name }}{{ $t->user->company ? ' · ' . $t->user->company : '' }}</dd></div>
                                            @if ($isAdmin)
                                                <div class="flex gap-2"><dt class="w-16 shrink-0 text-slate-400">Engineer</dt>
                                                    <dd class="min-w-0 truncate">{!! $t->assignee ? e($t->assignee->name) : '<span class="font-medium text-violet-600">Unassigned</span>' !!}</dd></div>
                                            @endif
                                            <div class="flex gap-2"><dt class="w-16 shrink-0 text-slate-400">Type</dt><dd>{{ $t->supportTypeLabel() ?? 'Not decided yet' }}</dd></div>
                                            @if ($t->location)
                                                <div class="flex gap-2"><dt class="w-16 shrink-0 text-slate-400">Where</dt>
                                                    <dd class="min-w-0">
                                                        @if ($t->mapsUrl())
                                                            <a href="{{ $t->mapsUrl() }}" target="_blank" rel="noopener" class="relative z-10 line-clamp-2 text-brand-700 hover:underline">{{ $t->location }}</a>
                                                        @else
                                                            {{ $t->location }}
                                                        @endif
                                                    </dd></div>
                                            @endif
                                        </dl>

                                        <p class="mt-3 inline-flex items-center gap-1 text-xs font-semibold text-brand-700 transition group-hover:gap-2">
                                            View ticket
                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m9 5 7 7-7 7"/></svg>
                                        </p>
                                    </div>
                                </li>
                            @endforeach
                        </ol>
                    @endif
                </div>
            @endforeach
        </aside>
    </div>
</x-app-layout>
