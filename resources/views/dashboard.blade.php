<x-app-layout>
    <x-slot name="header">Dashboard</x-slot>

    @php
        $user = auth()->user();
        $svg = fn ($paths) => '<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' . $paths . '</svg>';
        $cards = [
            ['Needs assignment', $stats['unassigned'],     route('tickets.index', ['status' => 'unassigned']),  'bg-violet-50 text-violet-600',   '<path d="M12 8v4l3 2"/><circle cx="12" cy="12" r="9"/>', 'unassigned'],
            ['Assigned',         $stats['assigned'],       route('tickets.index', ['status' => 'assigned']),    'bg-indigo-50 text-indigo-600',   '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0M17 8v6M14 11h6"/>', 'assigned'],
            ['In progress',      $stats['in_progress'],    route('tickets.index', ['status' => 'in_progress']), 'bg-amber-50 text-amber-600',     '<path d="M14.7 6.3a4 4 0 0 0-5.4 5.4L3 18l3 3 6.3-6.3a4 4 0 0 0 5.4-5.4l-2.4 2.4-2.6-.6-.6-2.6z"/>', 'in_progress'],
            ['High / Critical',  $stats['urgent'],         route('tickets.index'),                              'bg-orange-50 text-orange-600',   '<path d="M12 3l9 16H3L12 3z"/><path d="M12 10v4M12 17h.01"/>', 'urgent'],
            ['Overdue (SLA)',    $stats['overdue'],        route('tickets.index'),                              'bg-rose-50 text-rose-600',       '<circle cx="12" cy="13" r="8"/><path d="M12 9v4l2 2M9 3h6"/>', 'overdue'],
            ['Resolved (7 days)', $stats['resolved_week'], route('tickets.index', ['status' => 'resolved']),    'bg-emerald-50 text-emerald-600', '<circle cx="12" cy="12" r="9"/><path d="M8 12.5l2.7 2.7L16 9.5"/>', 'resolved_week'],
        ];
        $loadMax   = max(1, $engineers->max('active_tickets') ?? 1);
        $fmt = function ($m) {
            if ($m === null) return '-';
            return $m < 60 ? round($m) . 'm' : ($m < 1440 ? round($m / 60, 1) . 'h' : round($m / 1440, 1) . 'd');
        };
        $card = 'rounded-xl border border-slate-200 bg-white shadow-sm';
        $chartConfig = ['url' => route('dashboard.charts'), 'range' => '7d', 'initial' => $charts, 'pollMs' => 60000];
    @endphp

    {{-- Header: each role gets its own banner --}}
    @php
        $first = \Illuminate\Support\Str::of($user->name)->explode(' ')->first();
        $greeting = now()->hour < 12 ? 'Good morning' : (now()->hour < 18 ? 'Good afternoon' : 'Good evening');
        $ghost = 'rounded-lg px-3.5 py-2 text-sm font-medium text-white ring-1 ring-inset ring-white/30 transition hover:bg-white/10';
    @endphp

    @php
        $isSuper = $user->role === 'super_admin';
        $roleIcon = $isSuper ? \App\Support\RoleTheme::CROWN : \App\Support\RoleTheme::SHIELD;
    @endphp
    <section class="relative mb-4 overflow-hidden rounded-xl px-4 py-3 text-white shadow-sm sm:px-6 {{ $isSuper ? 'bg-gradient-to-br from-brand-900 to-brand-800 ring-1 ring-amber-400/30' : 'bg-gradient-to-r from-brand-800 to-brand-700' }}">
        <div class="relative flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-[11px] font-semibold {{ $isSuper ? 'bg-amber-400/15 text-amber-300 ring-1 ring-inset ring-amber-400/40' : 'bg-white/15 text-white ring-1 ring-inset ring-white/25' }}">
                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $roleIcon !!}</svg>
                        {{ $isSuper ? 'Super Admin' : $user->roleLabel() }}
                    </span>
                    <span class="text-xs text-brand-200">{{ now()->format('l, F j, Y') }}</span>
                </div>
                <h2 class="mt-1.5 text-xl font-bold tracking-tight sm:text-2xl">{{ $greeting }}, {{ $first }}</h2>
                <p class="mt-0.5 max-w-2xl text-sm text-brand-100">{{ $isSuper ? 'Full control of tickets, accounts and sign-ins in one place.' : ($user->isJmsAdmin() ? 'Accept new tickets from every partner, dispatch our JMS engineers and keep every request on track.' : 'Accept new tickets, assign your engineers and keep every request on track.') }}</p>
            </div>
            <div class="flex shrink-0 flex-wrap gap-2">
                @if ($isSuper)
                    <a href="{{ route('users.index') }}" class="rounded-lg bg-amber-400 px-3.5 py-2 text-sm font-semibold text-brand-900 shadow-sm transition hover:bg-amber-300">Manage accounts</a>
                @else
                    <a href="{{ route('tickets.index', ['status' => 'unassigned']) }}" class="rounded-lg bg-white px-3.5 py-2 text-sm font-semibold text-brand-800 shadow-sm transition hover:bg-brand-50">
                        Review queue
                        <span x-data x-show="$store.pending.count > 0" x-cloak x-text="$store.pending.count" class="ml-1.5 rounded-full bg-violet-600 px-1.5 py-0.5 text-xs font-bold text-white"></span>
                    </a>
                @endif
                <a href="{{ route('schedule.index') }}" class="{{ $ghost }}">Schedule</a>
            </div>
        </div>
    </section>

    @if ($system)
        @include('dashboard.partials.system-overview')
    @endif

    {{-- KPI cards: the numbers count up and stay live (see resources/js/charts.js) --}}
    <div x-data x-init="$store.dash.init(@js($stats))" hidden></div>
    <div class="mb-4 grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
        @foreach ($cards as [$label, $value, $href, $tone, $paths, $key])
            <a href="{{ $href }}" class="group flex items-center gap-3 rounded-xl border bg-white p-3 shadow-sm transition hover:border-brand-200 hover:shadow-md {{ $label === 'Overdue (SLA)' && $value > 0 ? 'border-rose-200' : 'border-slate-200' }}">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg {{ $tone }}">{!! $svg($paths) !!}</span>
                <div class="min-w-0">
                <p class="text-2xl font-bold leading-none tracking-tight text-slate-900 tabular-nums"
                   x-data x-count="{{ $key === 'unassigned' ? '$store.pending.count' : '$store.dash.kpis.' . $key }}">{{ $value }}</p>
                <p class="mt-1 truncate text-xs font-medium text-slate-500">{{ $label }}</p>
                </div>
            </a>
        @endforeach
    </div>

    {{-- Triage: waiting for acceptance (live) --}}
    <section x-data
             :class="$store.pending.count > 0 ? 'ring-2 ring-violet-200' : ''"
             class="{{ $card }} mb-4 overflow-hidden transition-shadow" aria-labelledby="pending-title">
        <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-4 py-3 transition-colors"
             :class="$store.pending.count > 0 ? 'bg-violet-50' : ''">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <h3 id="pending-title" class="font-semibold" :class="$store.pending.count > 0 ? 'text-violet-900' : 'text-brand-800'">Waiting for acceptance</h3>
                    <span x-show="$store.pending.count > 0" x-cloak x-text="$store.pending.count"
                          class="rounded-full bg-violet-600 px-2 py-0.5 text-xs font-bold leading-none text-white"
                          role="status" aria-live="polite"></span>
                </div>
                <p class="text-xs" :class="$store.pending.count > 0 ? 'text-violet-700' : 'text-slate-500'">Accept each ticket and assign an IT engineer, on-site or remote.</p>
            </div>

            <div class="flex shrink-0 flex-col items-end gap-1 sm:flex-row sm:items-center sm:gap-4">
                <span class="inline-flex items-center gap-1.5 text-xs font-medium text-slate-500" title="This list refreshes automatically">
                    <span class="relative flex h-2 w-2">
                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full opacity-75" :class="$store.pending.online ? 'bg-emerald-400' : 'bg-amber-400'"></span>
                        <span class="relative inline-flex h-2 w-2 rounded-full" :class="$store.pending.online ? 'bg-emerald-500' : 'bg-amber-500'"></span>
                    </span>
                    <span x-text="$store.pending.status"></span>
                    <span class="hidden text-slate-400 sm:inline" x-show="$store.pending.online" x-text="'· updated ' + $store.pending.updatedLabel"></span>
                </span>
                <a x-show="$store.pending.count > $store.pending.items.length" x-cloak
                   href="{{ route('tickets.index', ['status' => 'unassigned']) }}"
                   class="text-sm font-medium text-violet-700 hover:underline">View all <span x-text="$store.pending.count"></span></a>
            </div>
        </div>

        <template x-for="t in $store.pending.items" :key="t.id">
            <div class="pending-row flex flex-col gap-3 border-b border-slate-100 px-4 py-3 last:border-0 sm:flex-row sm:items-center sm:justify-between"
                 :class="$store.pending.isFresh(t.id) ? 'is-new bg-violet-50/70' : ''">
                <div class="flex min-w-0 items-start gap-3">
                    <template x-if="t.avatar">
                        <button type="button" :data-photo="t.avatar" :data-photo-name="t.requester" :aria-label="'View ' + t.requester + ' photo'" title="View photo" style="cursor:zoom-in"
                                class="inline-flex shrink-0 rounded-full transition hover:opacity-90 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2">
                            <img :src="t.avatar" alt="" loading="lazy" class="h-9 w-9 shrink-0 rounded-full object-cover ring-2 ring-white">
                        </button>
                    </template>
                    <template x-if="!t.avatar">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-brand-800 text-sm font-bold uppercase text-white ring-2 ring-white" x-text="t.initials" :aria-label="t.requester"></span>
                    </template>

                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <a :href="t.url" class="line-clamp-1 font-medium text-slate-900 hover:text-brand-700 hover:underline" x-text="t.subject"></a>
                            <span x-show="$store.pending.isFresh(t.id)" x-cloak class="shrink-0 rounded-full bg-violet-600 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-white">New</span>
                        </div>
                        <p class="line-clamp-1 text-xs text-slate-500">
                            <span x-text="t.ticket_no"></span> &middot; <span x-text="t.requester"></span><span x-show="t.company" x-text="' (' + t.company + ')'"></span> &middot; <span x-text="t.submitted"></span>
                        </p>
                        <div class="mt-1.5 flex flex-wrap items-center gap-2">
                            <span class="rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset" :class="t.priority_classes" x-text="t.priority_label"></span>
                            <span x-show="t.support_type" class="rounded-full bg-brand-50 px-2 py-0.5 text-xs font-medium text-brand-700" x-text="t.support_type"></span>
                            <span x-show="t.scheduled" class="rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-700" x-text="'Scheduled ' + t.scheduled"></span>
                            <span class="text-xs" :class="t.waiting_class" x-text="'Waiting ' + t.waiting"></span>
                            <span x-show="t.sla" class="text-xs" :class="t.sla ? t.sla.class : ''" x-text="t.sla ? t.sla.text : ''"></span>
                        </div>
                    </div>
                </div>
                <a :href="t.assign_url" class="shrink-0 rounded-lg bg-violet-600 px-4 py-2 text-center text-sm font-semibold text-white shadow-sm transition hover:bg-violet-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-violet-500 focus-visible:ring-offset-2">Accept &amp; assign</a>
            </div>
        </template>

        <div x-show="$store.pending.count === 0" x-cloak class="flex items-center gap-3 px-4 py-5">
            <span class="flex h-10 w-10 items-center justify-center rounded-full bg-emerald-50 text-emerald-600">{!! $svg('<path d="M5 13l4 4L19 7"/>') !!}</span>
            <div>
                <p class="font-medium text-slate-800">Nothing is waiting</p>
                <p class="text-sm text-slate-500">Every submitted ticket has been accepted and assigned. New tickets appear here the moment they are sent.</p>
            </div>
        </div>
    </section>

    <div class="grid gap-4 xl:grid-cols-3">
        {{-- Left: active work --}}
        <div class="space-y-4 xl:col-span-2">
            @if ($overdueTickets->isNotEmpty())
                @include('dashboard.partials.needs-attention')
            @endif

            @if ($partners !== null)
                @include('dashboard.partials.partner-overview')
            @endif

            <section class="{{ $card }} overflow-hidden">
                <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                    <div>
                        <h3 class="font-semibold text-brand-800">Active work</h3>
                        <p class="text-xs text-slate-500">Tickets with an engineer, most urgent first</p>
                    </div>
                    <a href="{{ route('tickets.index') }}" class="text-sm font-medium text-brand-600 hover:underline">View all tickets</a>
                </div>
                @php($barColor = ['low' => 'bg-slate-300', 'medium' => 'bg-sky-400', 'high' => 'bg-orange-500', 'critical' => 'bg-red-600'])
                @php($textColor = ['low' => 'text-slate-500', 'medium' => 'text-sky-700', 'high' => 'text-orange-600', 'critical' => 'text-red-600'])
                <ul class="divide-y divide-slate-100">
                    @forelse ($queue as $t)
                        @php($sla = $t->slaBadge())
                        <li>
                            <a href="{{ route('tickets.show', $t) }}" class="group relative flex items-center gap-3 py-3 pl-5 pr-4 transition hover:bg-brand-50/50 focus-visible:bg-brand-50/50 focus-visible:outline-none">
                                <span class="absolute inset-y-3 left-0 w-1 rounded-r-full {{ $barColor[$t->priority] ?? 'bg-slate-300' }}" aria-hidden="true"></span>

                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center justify-between gap-3">
                                        <p class="truncate font-semibold text-slate-900 group-hover:text-brand-700">{{ $t->subject }}</p>
                                        <x-ticket-pill :ticket="$t" class="shrink-0" />
                                    </div>
                                    <div class="mt-1 flex items-center justify-between gap-3 text-xs">
                                        <p class="min-w-0 truncate text-slate-500">
                                            <span class="font-medium {{ $textColor[$t->priority] ?? 'text-slate-500' }}">{{ $t->priorityLabel() }}</span>
                                            <span aria-hidden="true" class="px-1 text-slate-300">/</span>{{ $t->user->name }}
                                        </p>
                                        <p class="flex min-w-0 shrink-0 items-center gap-1.5 text-slate-600">
                                            <x-avatar :user="$t->assignee" size="h-5 w-5" text="text-[8px]" :zoom="false" />
                                            <span class="max-w-[7rem] truncate">{{ \Illuminate\Support\Str::before($t->assignee->name, ' ') }}</span>
                                        </p>
                                    </div>
                                    <p class="mt-0.5 truncate text-xs {{ $sla ? $sla[1] : 'text-slate-400' }}">
                                        <span class="font-mono text-[11px] text-slate-400">{{ $t->ticket_no }}</span>
                                        <span aria-hidden="true" class="px-1 text-slate-300">/</span>{{ $sla ? $sla[0] : 'On hold' }}
                                    </p>
                                </div>

                                <svg class="h-4 w-4 shrink-0 text-slate-300 transition group-hover:translate-x-0.5 group-hover:text-brand-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>
                            </a>
                        </li>
                    @empty
                        <li class="px-4 py-10 text-center">
                            <span class="mx-auto flex h-11 w-11 items-center justify-center rounded-full bg-slate-100 text-slate-400">
                                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 6h13M8 12h13M8 18h13M3.5 6h.01M3.5 12h.01M3.5 18h.01"/></svg>
                            </span>
                            <p class="mt-2 text-sm font-medium text-slate-700">No active work</p>
                            <p class="text-sm text-slate-500">Tickets appear here once an engineer is assigned.</p>
                        </li>
                    @endforelse
                </ul>
            </section>

            {{-- Charts: drawn with Chart.js and refreshed in the background (see resources/js/charts.js) --}}
            <div x-data="dashCharts(@js($chartConfig))" class="space-y-4">
                <section class="{{ $card }} p-4" aria-labelledby="trend-title">
                    <div class="mb-3 flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h3 id="trend-title" class="font-semibold text-brand-800">Ticket activity</h3>
                            <p class="text-xs text-slate-500">New and resolved tickets, <span x-text="rangeLabel"></span></p>
                        </div>
                        <div class="inline-flex rounded-lg bg-slate-100 p-0.5" role="group" aria-label="Time range">
                            <template x-for="r in ranges" :key="r.key">
                                <button type="button" @click="setRange(r.key)" :aria-pressed="range === r.key" x-text="r.label"
                                        :class="range === r.key ? 'bg-white text-brand-800 shadow-sm' : 'text-slate-500 hover:text-slate-700'"
                                        class="rounded-md px-2.5 py-1 text-xs font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500"></button>
                            </template>
                        </div>
                    </div>

                    <div class="mb-3 flex flex-wrap items-center gap-x-5 gap-y-1 text-xs text-slate-500">
                        <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-brand-500"></span><span class="font-semibold tabular-nums text-slate-800" x-text="data.trend.totals.created"></span> new</span>
                        <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-emerald-500"></span><span class="font-semibold tabular-nums text-slate-800" x-text="data.trend.totals.resolved"></span> resolved</span>
                        <span x-show="!trendEmpty" x-cloak title="New tickets minus resolved tickets in this period">
                            Backlog <span class="font-semibold tabular-nums" :class="net > 0 ? 'text-rose-600' : 'text-emerald-600'" x-text="(net > 0 ? '+' : '') + net"></span>
                        </span>
                        <span class="ml-auto inline-flex items-center gap-1.5" title="These charts refresh automatically">
                            <span class="h-1.5 w-1.5 rounded-full" :class="failed ? 'bg-amber-500' : 'bg-emerald-500'"></span>
                            <span x-text="failed ? 'Offline, showing last data' : 'Updated ' + updatedLabel"></span>
                        </span>
                    </div>

                    <div class="relative h-56">
                        <canvas x-ref="trend" role="img" :aria-label="trendSummary"></canvas>
                        <p x-show="trendEmpty" x-cloak class="absolute inset-0 flex items-center justify-center rounded-lg bg-white/80 text-sm text-slate-500">No ticket activity in this period.</p>
                    </div>
                </section>

                <div class="grid gap-4 md:grid-cols-2">
                    <section class="{{ $card }} p-4">
                        <div class="mb-3 flex items-center justify-between">
                            <h3 class="font-semibold text-brand-800">Tickets by status</h3>
                            <a href="{{ route('tickets.index') }}" class="text-xs font-medium text-brand-600 hover:underline">All tickets</a>
                        </div>
                        <div class="flex items-center gap-4">
                            <div class="relative h-32 w-32 shrink-0">
                                <canvas x-ref="status" role="img" :aria-label="statusSummary"></canvas>
                            </div>
                            <ul class="min-w-0 flex-1 space-y-0.5">
                                <template x-for="s in data.status" :key="s.key">
                                    <li>
                                        <a :href="s.url" class="flex items-center justify-between gap-2 rounded-md px-1.5 py-1 text-xs transition hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500">
                                            <span class="flex min-w-0 items-center gap-2">
                                                <span class="h-2.5 w-2.5 shrink-0 rounded-full" :style="'background:' + s.color"></span>
                                                <span class="truncate font-medium text-slate-600" x-text="s.label"></span>
                                            </span>
                                            <span class="font-semibold tabular-nums" :class="s.value ? 'text-slate-800' : 'text-slate-300'" x-text="s.value"></span>
                                        </a>
                                    </li>
                                </template>
                            </ul>
                        </div>
                    </section>

                    <section class="{{ $card }} p-4">
                        <h3 class="mb-3 font-semibold text-brand-800">Top categories</h3>
                        <div class="relative h-44">
                            <canvas x-ref="cats" role="img" aria-label="Bar chart of the most requested ticket categories"></canvas>
                            <p x-show="data.categories.length === 0" x-cloak class="absolute inset-0 flex items-center justify-center text-sm text-slate-500">No tickets yet.</p>
                        </div>
                    </section>
                </div>
            </div>
        </div>

        {{-- Right column --}}
        <div class="space-y-4">
            @include('dashboard.partials.upcoming-schedule')

            {{-- Service quality --}}
            <section class="{{ $card }} p-4">
                <h3 class="mb-3 font-semibold text-brand-800">Service quality</h3>
                <div class="grid grid-cols-3 gap-3 text-center">
                    <div class="rounded-xl bg-slate-50 p-3">
                        <p class="text-xl font-bold text-slate-900">{{ $fmt($quality['avg_minutes']) }}</p>
                        <p class="mt-0.5 text-[11px] font-medium text-slate-500">Avg. resolution</p>
                    </div>
                    <div class="rounded-xl bg-slate-50 p-3">
                        <p class="text-xl font-bold text-slate-900">{{ $quality['sla_rate'] === null ? '-' : $quality['sla_rate'] . '%' }}</p>
                        <p class="mt-0.5 text-[11px] font-medium text-slate-500">Within target</p>
                    </div>
                    <div class="rounded-xl bg-slate-50 p-3">
                        <p class="text-xl font-bold text-slate-900">{{ $quality['rating'] ? number_format($quality['rating'], 1) : '-' }}</p>
                        <p class="mt-0.5 text-[11px] font-medium text-slate-500">Rating{{ $quality['rated'] ? ' (' . $quality['rated'] . ')' : '' }}</p>
                    </div>
                </div>
            </section>

            {{-- Engineers --}}
            <section class="{{ $card }} p-4">
                <div class="mb-3 flex items-center justify-between">
                    <h3 class="font-semibold text-brand-800">Engineer workload</h3>
                    <a href="{{ route('users.index', ['role' => 'it_support']) }}" class="text-xs font-medium text-brand-600 hover:underline">Manage</a>
                </div>
                <div class="space-y-3">
                    @forelse ($engineers as $e)
                        <div>
                            <div class="mb-1.5 flex items-center justify-between gap-3">
                                <div class="flex min-w-0 items-center gap-2.5">
                                    <x-avatar :user="$e" size="h-8 w-8" text="text-xs" />
                                    <span class="truncate text-sm font-medium text-slate-800">{{ $e->name }}</span>
                                </div>
                                <span class="shrink-0 text-xs font-semibold {{ $e->active_tickets ? 'text-slate-700' : 'text-emerald-600' }}">{{ $e->active_tickets ? $e->active_tickets . ' active' : 'Free' }}</span>
                            </div>
                            <div class="h-1.5 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-indigo-500" style="width: {{ $e->active_tickets / $loadMax * 100 }}%"></div></div>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">No IT Support accounts yet. Add one from User Management.</p>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
