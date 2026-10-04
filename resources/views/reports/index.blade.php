<x-app-layout>
    <x-slot name="header">Reports</x-slot>

    @php
        $card = 'rounded-xl border border-slate-200 bg-white shadow-sm';
        $hours = fn ($h) => $h === null ? '-' : ($h < 24 ? number_format($h, 1) . ' h' : number_format($h / 24, 1) . ' days');
        $statusColor = ['open' => 'bg-blue-500', 'assigned' => 'bg-indigo-500', 'in_progress' => 'bg-amber-500', 'on_hold' => 'bg-slate-400', 'resolved' => 'bg-emerald-500', 'closed' => 'bg-teal-600', 'cancelled' => 'bg-rose-400'];
        $priorityColor = ['low' => 'bg-slate-400', 'medium' => 'bg-sky-500', 'high' => 'bg-orange-500', 'critical' => 'bg-red-600'];
        $roleLabels = \App\Models\User::ROLE_LABELS;
        $qs = request()->only(['range', 'from', 'to']);
        $periodText = $from && $to ? $from->format('M j, Y') . ' to ' . $to->format('M j, Y') : 'Every ticket since the first one';
        $chip = function (?array $c) {
            if (! $c || $c['text'] === null) return null;
            $tone = $c['good'] === null ? 'bg-slate-100 text-slate-600' : ($c['good'] ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700');
            return [$c['text'], $tone];
        };
        $tiles = [
            ['Tickets received', number_format($stats['total']), $stats['cancelled'] ? $stats['cancelled'] . ' cancelled' : 'None cancelled', $chip($compare['total'] ?? null), 'border-t-brand-500'],
            ['Resolved or closed', $stats['rate'] === null ? '-' : $stats['rate'] . '%', $stats['done'] . ' of ' . ($stats['total'] - $stats['cancelled']) . ' tickets', $chip($compare['rate'] ?? null), 'border-t-emerald-500'],
            ['Average fix time', $hours($stats['avgHours']), 'From submitted to resolved', $chip($compare['avgHours'] ?? null), 'border-t-amber-400'],
            ['Met target time', $stats['metPct'] === null ? '-' : $stats['metPct'] . '%', 'Of resolved tickets', $chip($compare['metPct'] ?? null), 'border-t-indigo-500'],
            ['Satisfaction', $stats['rating'] === null ? '-' : $stats['rating'] . ' / 5', $stats['ratingCount'] ? $stats['ratingCount'] . ' ' . \Illuminate\Support\Str::plural('rating', $stats['ratingCount']) : 'No ratings yet', $chip($compare['rating'] ?? null), 'border-t-violet-500'],
        ];
    @endphp

    <style>
        .print-only { display: none; }
        @media print {
            aside, header, .no-print { display: none !important; }
            .lg\:pl-64 { padding-left: 0 !important; }
            body { background: #fff !important; }
            main { max-width: none !important; padding: 0 !important; }
            .print-only { display: block !important; }
            section, .keep-together { break-inside: avoid; }
            * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>

    {{-- Printed copy header (the on-screen header is hidden when printing) --}}
    <div class="print-only mb-4 border-b-2 border-brand-800 pb-3">
        <p class="text-xs font-semibold text-slate-500">JMS One IT service desk</p>
        <h1 class="text-2xl font-bold text-brand-800">IT service report: {{ $label }}</h1>
        <p class="text-xs text-slate-500">{{ $periodText }}. Prepared by {{ auth()->user()->name }} on {{ now()->format('M j, Y, g:i A') }}.</p>
    </div>

    {{-- Title and actions --}}
    <div class="no-print mb-4 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h2 class="text-xl font-extrabold tracking-tight text-brand-800">Service desk report</h2>
            <p class="mt-0.5 text-sm text-slate-500">How the team is doing: tickets, speed, workload and satisfaction.</p>
        </div>
        <div class="flex shrink-0 gap-2">
            <button type="button" onclick="window.print()" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50">
                <svg class="h-4 w-4 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 9V3h10v6M7 17H5a1 1 0 0 1-1-1v-5a1 1 0 0 1 1-1h14a1 1 0 0 1 1 1v5a1 1 0 0 1-1 1h-2M7 14h10v7H7z"/></svg>
                Print or save as PDF
            </button>
            <a href="{{ route('reports.export', $qs) }}" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-800 px-3.5 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 4v11m0 0-4-4m4 4 4-4M4 19h16"/></svg>
                Download Excel (.xlsx)
            </a>
        </div>
    </div>

    {{-- Period picker --}}
    <section class="no-print {{ $card }} mb-4 p-3 sm:p-4" x-data="{ custom: {{ $range === 'custom' ? 'true' : 'false' }} }">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex flex-wrap gap-1.5" role="group" aria-label="Report period">
                @foreach ($ranges as $k => $name)
                    @if ($k === 'custom')
                        <button type="button" @click="custom = ! custom" :aria-pressed="custom"
                                class="rounded-full px-3.5 py-1.5 text-sm font-medium transition {{ $range === 'custom' ? 'bg-brand-800 text-white shadow-sm' : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50' }}">{{ $name }} dates</button>
                    @else
                        <a href="{{ route('reports.index', ['range' => $k]) }}" @if ($range === $k) aria-current="true" @endif
                           class="rounded-full px-3.5 py-1.5 text-sm font-medium transition {{ $range === $k ? 'bg-brand-800 text-white shadow-sm' : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50' }}">{{ $name }}</a>
                    @endif
                @endforeach
            </div>
            <p class="text-sm text-slate-500"><span class="font-semibold text-slate-800">{{ $label }}</span>
                @if ($from && $to && ! in_array($range, ['custom'])) <span class="text-slate-400">&middot;</span> {{ $periodText }} @endif</p>
        </div>
        <form method="GET" action="{{ route('reports.index') }}" x-show="custom" x-cloak class="mt-3 flex flex-wrap items-end gap-3 border-t border-slate-100 pt-3">
            <input type="hidden" name="range" value="custom">
            <div>
                <label for="rep-from" class="mb-1 block text-xs font-medium text-slate-600">From</label>
                <input id="rep-from" type="date" name="from" value="{{ request('from', $from?->format('Y-m-d')) }}" class="rounded-lg border-slate-300 py-1.5 text-sm focus:border-brand-500 focus:ring-brand-500">
            </div>
            <div>
                <label for="rep-to" class="mb-1 block text-xs font-medium text-slate-600">To</label>
                <input id="rep-to" type="date" name="to" value="{{ request('to', $to?->format('Y-m-d')) }}" class="rounded-lg border-slate-300 py-1.5 text-sm focus:border-brand-500 focus:ring-brand-500">
            </div>
            <button class="rounded-lg bg-brand-800 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">Show report</button>
        </form>
    </section>

    {{-- Plain-language summary --}}
    <section class="{{ $card }} mb-4 flex items-start gap-3 border-l-4 border-l-brand-500 p-4">
        <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-brand-700">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 3h8l4 4v14H7zM15 3v4h4M10 12h6M10 16h6"/></svg>
        </span>
        <div>
            <h3 class="text-sm font-semibold text-brand-800">Summary for {{ $label }}</h3>
            <p class="mt-0.5 text-sm leading-relaxed text-slate-600">{{ $summary }}</p>
            @if ($compare) <p class="no-print mt-1 text-xs text-slate-400">Arrows compare with the period right before this one.</p> @endif
        </div>
    </section>

    {{-- Key numbers --}}
    <div class="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-5">
        @foreach ($tiles as [$name, $value, $note, $change, $border])
            <div class="keep-together rounded-xl border border-t-2 border-slate-200 {{ $border }} bg-white px-4 py-3 shadow-sm">
                <p class="text-xs font-medium text-slate-500">{{ $name }}</p>
                <p class="mt-1 text-2xl font-bold tracking-tight text-slate-900">{{ $value }}</p>
                <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1">
                    <span class="text-xs text-slate-500">{{ $note }}</span>
                    @if ($change) <span class="rounded-full px-1.5 py-0.5 text-[11px] font-semibold {{ $change[1] }}">{{ $change[0] }}</span> @endif
                </div>
            </div>
        @endforeach
    </div>

    {{-- Volume trend + status --}}
    <div class="mb-4 grid gap-4 xl:grid-cols-3">
        <section class="{{ $card }} p-4 xl:col-span-2" aria-labelledby="trend-title">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h3 id="trend-title" class="font-semibold text-brand-800">Ticket volume</h3>
                    <p class="text-xs text-slate-500">Submitted and resolved, by {{ $trend['unit'] }}</p>
                </div>
                <div class="flex items-center gap-4 text-xs text-slate-500">
                    <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-brand-500"></span>Submitted</span>
                    <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-emerald-500"></span>Resolved</span>
                </div>
            </div>
            @if (collect($trend['points'])->sum(fn ($p) => $p['created'] + $p['resolved']) === 0)
                <div class="flex h-44 items-center justify-center rounded-lg bg-slate-50 text-sm text-slate-500">No ticket activity in this period.</div>
            @else
                @php($dense = count($trend['points']) > 16)
                <div class="flex h-44 items-end gap-px border-b border-slate-200 pt-5 sm:gap-0.5">
                    @foreach ($trend['points'] as $p)
                        <div class="flex h-full min-w-0 flex-1 items-end justify-center gap-px" title="{{ $p['full'] }}: {{ $p['created'] }} submitted, {{ $p['resolved'] }} resolved">
                            @foreach ([['created', 'bg-brand-500', 'text-brand-600'], ['resolved', 'bg-emerald-500', 'text-emerald-600']] as [$k, $bg, $tx])
                                <div class="flex h-full w-full max-w-[1.25rem] flex-col items-center justify-end">
                                    @if ($p[$k] && ! $dense)<span class="mb-0.5 text-[10px] font-semibold leading-none {{ $tx }}">{{ $p[$k] }}</span>@endif
                                    <div class="w-full rounded-t {{ $bg }}" style="height: {{ $p[$k] ? max($p[$k] / $trend['max'] * 100, 4) : 0 }}%"></div>
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                </div>
                <div class="mt-1.5 flex gap-px sm:gap-0.5">
                    @foreach ($trend['points'] as $i => $p)
                        <span class="min-w-0 flex-1 truncate text-center text-[10px] text-slate-500 {{ $dense && $i % 2 ? 'invisible' : '' }}">{{ $p['label'] }}</span>
                    @endforeach
                </div>
            @endif
        </section>

        <section class="{{ $card }} p-4" aria-labelledby="status-title">
            <h3 id="status-title" class="font-semibold text-brand-800">Where tickets stand</h3>
            <p class="text-xs text-slate-500">Current status of the {{ $stats['total'] }} {{ \Illuminate\Support\Str::plural('ticket', $stats['total']) }} received</p>
            @if ($stats['total'] === 0)
                <p class="mt-6 text-sm text-slate-500">Nothing to show for this period.</p>
            @else
                <div class="mt-3 flex h-3 overflow-hidden rounded-full bg-slate-100" role="img" aria-label="Share of tickets by status">
                    @foreach ($status as $s) <div class="{{ $statusColor[$s['key']] ?? 'bg-slate-400' }}" style="width: {{ $s['percent'] }}%" title="{{ $s['label'] }}: {{ $s['value'] }}"></div> @endforeach
                </div>
                <ul class="mt-3 space-y-2">
                    @foreach ($status as $s)
                        <li class="flex items-center justify-between gap-3 text-sm">
                            <span class="flex items-center gap-2 text-slate-700"><span class="h-2.5 w-2.5 rounded-full {{ $statusColor[$s['key']] ?? 'bg-slate-400' }}"></span>{{ $s['label'] }}</span>
                            <span class="font-semibold text-slate-900">{{ $s['value'] }} <span class="ml-1 font-normal text-slate-400">{{ $s['percent'] }}%</span></span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>

    {{-- Priority, category, support type --}}
    <div class="mb-4 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach ([['By priority', 'How urgent the requests were', $priority, $priorityColor], ['By category', 'What people needed help with', $category, []]] as [$title, $sub, $rows, $colors])
            <section class="{{ $card }} p-4">
                <h3 class="font-semibold text-brand-800">{{ $title }}</h3>
                <p class="mb-3 text-xs text-slate-500">{{ $sub }}</p>
                @forelse ($rows as $r)
                    <div class="mb-2.5 last:mb-0">
                        <div class="mb-1 flex items-center justify-between gap-3 text-sm">
                            <span class="truncate text-slate-700">{{ $r['label'] }}</span>
                            <span class="shrink-0 font-semibold text-slate-900">{{ $r['value'] }}</span>
                        </div>
                        <div class="h-2 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full {{ $colors[$r['key']] ?? 'bg-brand-500' }}" style="width: {{ max($r['percent'], 3) }}%"></div></div>
                    </div>
                @empty
                    <p class="py-4 text-sm text-slate-500">Nothing to show for this period.</p>
                @endforelse
            </section>
        @endforeach

        <section class="{{ $card }} p-4 md:col-span-2 xl:col-span-1">
            <h3 class="font-semibold text-brand-800">How help was given</h3>
            <p class="mb-3 text-xs text-slate-500">Remote support compared with on-site visits</p>
            @php($remote = $support['remote'] ?? 0)
            @php($onsite = $support['onsite'] ?? 0)
            @if ($remote + $onsite === 0)
                <p class="py-4 text-sm text-slate-500">No support type was chosen for these tickets yet.</p>
            @else
                <div class="grid grid-cols-2 gap-3">
                    @foreach ([['Remote', $remote, 'text-sky-700 bg-sky-50'], ['On-site', $onsite, 'text-violet-700 bg-violet-50']] as [$n, $v, $tone])
                        <div class="rounded-lg px-4 py-3 {{ $tone }}">
                            <p class="text-2xl font-bold">{{ $v }}</p>
                            <p class="text-xs font-medium">{{ $n }} &middot; {{ round($v / ($remote + $onsite) * 100) }}%</p>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>
    </div>

    {{-- Engineers --}}
    <section class="{{ $card }} mb-4 overflow-hidden" aria-labelledby="eng-title">
        <div class="border-b border-slate-100 px-4 py-3">
            <h3 id="eng-title" class="font-semibold text-brand-800">Engineer performance</h3>
            <p class="text-xs text-slate-500">For the tickets received in this period</p>
        </div>
        @if (empty($engineers))
            <p class="px-4 py-8 text-center text-sm text-slate-500">No tickets were assigned to an engineer in this period.</p>
        @else
            <table class="w-full table-fixed text-sm">
                <thead class="border-b border-slate-200 bg-slate-50/80 text-xs font-semibold text-slate-600">
                    <tr>
                        <th scope="col" class="w-2/5 py-2.5 pl-4 pr-3 text-left sm:w-1/3">Engineer</th>
                        <th scope="col" class="px-2 py-2.5 text-right">Assigned</th>
                        <th scope="col" class="px-2 py-2.5 text-right">Resolved</th>
                        <th scope="col" class="hidden px-2 py-2.5 text-right md:table-cell">Active</th>
                        <th scope="col" class="hidden px-2 py-2.5 text-right sm:table-cell">Avg fix time</th>
                        <th scope="col" class="hidden px-2 py-2.5 text-right lg:table-cell">Met target</th>
                        <th scope="col" class="py-2.5 pl-2 pr-4 text-right">Rating</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($engineers as $e)
                        <tr>
                            <td class="py-2.5 pl-4 pr-3">
                                <div class="flex min-w-0 items-center gap-2.5">
                                    <x-avatar :user="$e['user']" size="h-8 w-8" text="text-[10px]" />
                                    <a href="{{ route('users.show', $e['user']) }}" class="truncate font-medium text-slate-900 hover:text-brand-700 hover:underline">{{ $e['user']->name }}</a>
                                </div>
                            </td>
                            <td class="px-2 py-2.5 text-right font-semibold text-slate-900">{{ $e['assigned'] }}</td>
                            <td class="px-2 py-2.5 text-right text-emerald-700">{{ $e['done'] }}</td>
                            <td class="hidden px-2 py-2.5 text-right text-slate-600 md:table-cell">{{ $e['active'] }}</td>
                            <td class="hidden px-2 py-2.5 text-right text-slate-600 sm:table-cell">{{ $hours($e['avgHours']) }}</td>
                            <td class="hidden px-2 py-2.5 text-right lg:table-cell">
                                @if ($e['metPct'] === null) <span class="text-slate-400">-</span>
                                @else <span class="font-medium {{ $e['metPct'] >= 80 ? 'text-emerald-700' : ($e['metPct'] >= 50 ? 'text-amber-600' : 'text-rose-600') }}">{{ $e['metPct'] }}%</span> @endif
                            </td>
                            <td class="py-2.5 pl-2 pr-4 text-right">
                                @if ($e['rating'] === null) <span class="text-slate-400">-</span>
                                @else <span class="inline-flex items-center gap-1 font-medium text-slate-800"><svg class="h-3.5 w-3.5 text-amber-400" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="m12 3 2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1L3.2 9.5l6.1-.9z"/></svg>{{ $e['rating'] }}</span> @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>

    {{-- Requesters, accounts, right now --}}
    <div class="grid gap-4 lg:grid-cols-3">
        <section class="{{ $card }} p-4">
            <h3 class="font-semibold text-brand-800">Top requesting companies</h3>
            <p class="mb-3 text-xs text-slate-500">Who submitted the most tickets</p>
            @php($topMax = max(1, collect($companies)->max('value')))
            @forelse ($companies as $c)
                <div class="mb-2.5 last:mb-0">
                    <div class="mb-1 flex items-center justify-between gap-3 text-sm"><span class="truncate text-slate-700">{{ $c['name'] }}</span><span class="shrink-0 font-semibold text-slate-900">{{ $c['value'] }}</span></div>
                    <div class="h-2 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-brand-500" style="width: {{ max($c['value'] / $topMax * 100, 4) }}%"></div></div>
                </div>
            @empty
                <p class="py-4 text-sm text-slate-500">Nothing to show for this period.</p>
            @endforelse
        </section>

        <section class="{{ $card }} p-4">
            <h3 class="font-semibold text-brand-800">People</h3>
            <p class="mb-3 text-xs text-slate-500">{{ $accounts['total'] }} accounts in total, {{ $accounts['added'] }} added in this period</p>
            <ul class="space-y-2">
                @foreach ($roleLabels as $r => $name)
                    <li class="flex items-center justify-between text-sm"><span class="text-slate-700">{{ $name }}</span><span class="font-semibold text-slate-900">{{ $accounts['byRole'][$r] ?? 0 }}</span></li>
                @endforeach
            </ul>
        </section>

        <section class="{{ $card }} p-4">
            <h3 class="font-semibold text-brand-800">Right now</h3>
            <p class="mb-3 text-xs text-slate-500">Live numbers, whatever period is selected</p>
            <div class="grid grid-cols-2 gap-2.5">
                @foreach ([['Waiting for acceptance', $now['waiting'], 'bg-violet-50 text-violet-700'], ['Active tickets', $now['active'], 'bg-sky-50 text-sky-700'], ['Past target time', $now['overdue'], $now['overdue'] ? 'bg-rose-50 text-rose-700' : 'bg-emerald-50 text-emerald-700'], ['On hold', $now['onHold'], 'bg-slate-100 text-slate-700']] as [$n, $v, $tone])
                    <div class="rounded-lg px-3 py-2.5 {{ $tone }}"><p class="text-xl font-bold">{{ $v }}</p><p class="text-xs font-medium leading-tight">{{ $n }}</p></div>
                @endforeach
            </div>
        </section>
    </div>
</x-app-layout>
