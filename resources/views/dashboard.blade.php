<x-app-layout>
    <x-slot name="header">Dashboard</x-slot>

    @php
        $user = auth()->user();
        $svg = fn ($paths) => '<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' . $paths . '</svg>';
        $cards = [
            ['Needs assignment', $stats['unassigned'],     route('tickets.index', ['status' => 'unassigned']),  'bg-violet-50 text-violet-600',   '<path d="M12 8v4l3 2"/><circle cx="12" cy="12" r="9"/>'],
            ['Assigned',         $stats['assigned'],       route('tickets.index', ['status' => 'assigned']),    'bg-indigo-50 text-indigo-600',   '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0M17 8v6M14 11h6"/>'],
            ['In progress',      $stats['in_progress'],    route('tickets.index', ['status' => 'in_progress']), 'bg-amber-50 text-amber-600',     '<path d="M14.7 6.3a4 4 0 0 0-5.4 5.4L3 18l3 3 6.3-6.3a4 4 0 0 0 5.4-5.4l-2.4 2.4-2.6-.6-.6-2.6z"/>'],
            ['High / Critical',  $stats['urgent'],         route('tickets.index'),                              'bg-orange-50 text-orange-600',   '<path d="M12 3l9 16H3L12 3z"/><path d="M12 10v4M12 17h.01"/>'],
            ['Overdue (SLA)',    $stats['overdue'],        route('tickets.index'),                              'bg-rose-50 text-rose-600',       '<circle cx="12" cy="13" r="8"/><path d="M12 9v4l2 2M9 3h6"/>'],
            ['Resolved (7 days)', $stats['resolved_week'], route('tickets.index', ['status' => 'resolved']),    'bg-emerald-50 text-emerald-600', '<circle cx="12" cy="12" r="9"/><path d="M8 12.5l2.7 2.7L16 9.5"/>'],
        ];
        $statusColors = ['open' => 'bg-blue-500', 'assigned' => 'bg-indigo-500', 'in_progress' => 'bg-amber-500', 'on_hold' => 'bg-slate-400', 'resolved' => 'bg-emerald-500', 'closed' => 'bg-slate-300', 'cancelled' => 'bg-rose-400'];
        $statusMax = max(1, $byStatus->max() ?? 1);
        $catMax    = max(1, $byCategory->max() ?? 1);
        $trendMax  = max(1, $trend->max(fn ($d) => max($d['created'], $d['resolved'])));
        $loadMax   = max(1, $engineers->max('active_tickets') ?? 1);
        $fmt = function ($m) {
            if ($m === null) return '-';
            return $m < 60 ? round($m) . 'm' : ($m < 1440 ? round($m / 60, 1) . 'h' : round($m / 1440, 1) . 'd');
        };
        $card = 'rounded-xl border border-slate-200 bg-white shadow-sm';
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
                        {{ $isSuper ? 'Super Admin' : 'Admin' }}
                    </span>
                    <span class="text-xs text-brand-200">{{ now()->format('l, F j, Y') }}</span>
                </div>
                <h2 class="mt-1.5 text-xl font-bold tracking-tight sm:text-2xl">{{ $greeting }}, {{ $first }}</h2>
                <p class="mt-0.5 max-w-2xl text-sm text-brand-100">{{ $isSuper ? 'Full control of tickets, accounts and sign-ins in one place.' : 'Accept new tickets, assign your engineers and keep every request on track.' }}</p>
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

    {{-- KPI cards --}}
    <div class="mb-4 grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
        @foreach ($cards as [$label, $value, $href, $tone, $paths])
            <a href="{{ $href }}" class="group flex items-center gap-3 rounded-xl border bg-white p-3 shadow-sm transition hover:border-brand-200 hover:shadow-md {{ $label === 'Overdue (SLA)' && $value > 0 ? 'border-rose-200' : 'border-slate-200' }}">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg {{ $tone }}">{!! $svg($paths) !!}</span>
                <div class="min-w-0">
                @if ($label === 'Needs assignment')
                    <p class="text-2xl font-bold leading-none tracking-tight text-slate-900" x-data x-text="$store.pending.count">{{ $value }}</p>
                @else
                    <p class="text-2xl font-bold leading-none tracking-tight text-slate-900">{{ $value }}</p>
                @endif
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
                        <img :src="t.avatar" :alt="t.requester" loading="lazy" class="h-9 w-9 shrink-0 rounded-full object-cover ring-2 ring-white">
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
                                            <x-avatar :user="$t->assignee" size="h-5 w-5" text="text-[8px]" />
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

            {{-- 7-day trend --}}
            <section class="{{ $card }} p-4">
                <div class="mb-3 flex items-center justify-between">
                    <h3 class="font-semibold text-brand-800">Last 7 days</h3>
                    <div class="flex items-center gap-4 text-xs text-slate-500">
                        <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-brand-500"></span>Created</span>
                        <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-emerald-500"></span>Resolved</span>
                    </div>
                </div>
                <div class="flex h-36 items-end gap-2 border-b border-slate-200 pt-5 sm:gap-4">
                    @foreach ($trend as $d)
                        <div class="flex h-full flex-1 items-end justify-center gap-1">
                            @foreach ([['created', 'bg-brand-500', 'text-brand-600'], ['resolved', 'bg-emerald-500', 'text-emerald-600']] as [$k, $bg, $tx])
                                <div class="flex h-full w-full max-w-[1.5rem] flex-col items-center justify-end" title="{{ $d[$k] }} {{ $k }}">
                                    @if ($d[$k])<span class="mb-0.5 text-[10px] font-semibold leading-none {{ $tx }}">{{ $d[$k] }}</span>@endif
                                    <div class="w-full rounded-t {{ $bg }}" style="height: {{ max($d[$k] / $trendMax * 100, $d[$k] ? 6 : 0) }}%"></div>
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                </div>
                <div class="mt-1.5 flex gap-2 sm:gap-4">
                    @foreach ($trend as $d)
                        <span class="flex-1 text-center text-xs text-slate-500">{{ $d['label'] }}</span>
                    @endforeach
                </div>
            </section>

            {{-- Breakdowns sit under the chart so the left and right columns finish together --}}
            <div class="grid gap-4 md:grid-cols-2">
                <section class="{{ $card }} p-4">
                    <h3 class="mb-3 font-semibold text-brand-800">Tickets by status</h3>
                    <div class="space-y-2.5">
                        @foreach (\App\Models\Ticket::STATUSES as $s)
                            @php($n = $byStatus[$s] ?? 0)
                            <div>
                                <div class="mb-1 flex justify-between text-xs"><span class="font-medium text-slate-600">{{ ucwords(str_replace('_', ' ', $s)) }}</span><span class="text-slate-500">{{ $n }}</span></div>
                                <div class="h-2 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full {{ $statusColors[$s] }}" style="width: {{ $n / $statusMax * 100 }}%"></div></div>
                            </div>
                        @endforeach
                    </div>
                </section>

                <section class="{{ $card }} p-4">
                    <h3 class="mb-3 font-semibold text-brand-800">Top categories</h3>
                    <div class="space-y-3">
                        @forelse ($byCategory as $cat => $n)
                            <div>
                                <div class="mb-1 flex justify-between text-xs"><span class="font-medium text-slate-600">{{ $cat }}</span><span class="text-slate-500">{{ $n }}</span></div>
                                <div class="h-2 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-brand-500" style="width: {{ $n / $catMax * 100 }}%"></div></div>
                            </div>
                        @empty
                            <p class="text-sm text-slate-500">No data yet.</p>
                        @endforelse
                    </div>
                </section>
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
