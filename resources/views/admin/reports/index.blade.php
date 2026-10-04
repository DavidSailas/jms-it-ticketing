<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Reports</h2>
            <a href="{{ route('admin.reports.export.pdf', request()->query()) }}"
               class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-lg text-sm font-medium text-white shadow-sm hover:opacity-90 transition" style="background-color:#1a6b3c;">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0l-4-4m4 4l4-4M4 17v2a2 2 0 002 2h12a2 2 0 002-2v-2" /></svg>
                Export as PDF
            </a>
        </div>
    </x-slot>

    <div class="py-6 sm:py-8 max-w-[80rem] mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        {{-- Report banner: who this is for, what period, and the control to change it --}}
        <div class="rounded-2xl overflow-hidden shadow-sm border border-gray-100">
            <div class="px-5 sm:px-8 py-6 sm:py-7 text-white" style="background: linear-gradient(135deg, #123f24 0%, #1a6b3c 55%, #228a4a 100%);">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-widest text-green-100/80">Crest Forwarder Inc. · IT Service Desk</p>
                        <h1 class="text-2xl sm:text-[28px] font-semibold mt-1">Management Report</h1>
                        <p class="text-green-50/90 text-sm mt-1">{{ $rangeLabel }} <span class="text-green-100/50">·</span> Generated {{ now()->format('F j, Y') }}</p>
                    </div>
                    <div class="shrink-0 w-14 h-14 rounded-xl bg-white/10 ring-1 ring-white/20 flex items-center justify-center backdrop-blur-sm">
                        <img src="{{ asset('images/logo.png') }}" alt="Crest Forwarder Inc." class="w-9 h-9 object-contain">
                    </div>
                </div>

                <p class="mt-5 text-sm leading-relaxed text-green-50/95 max-w-3xl border-t border-white/15 pt-4">{{ $summary }}</p>
            </div>

            {{-- Period picker --}}
            <form method="GET" action="{{ route('admin.reports.index') }}" x-data="{ range: '{{ $selectedRange }}' }"
                  class="bg-white px-5 sm:px-8 py-3.5 flex flex-wrap items-end gap-3 border-t border-gray-100">
                <div>
                    <label class="block text-[11px] font-semibold uppercase tracking-wide text-gray-500 mb-1">Reporting period</label>
                    <select name="range" x-model="range" onchange="this.form.submit()" class="rounded-lg border-gray-300 text-sm focus:border-green-700 focus:ring-green-700 min-w-[11rem]">
                        @foreach($ranges as $value => $label)
                            <option value="{{ $value }}" @selected($selectedRange === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div x-show="range === 'custom'" x-cloak class="flex items-end gap-2">
                    <div>
                        <label class="block text-[11px] font-semibold uppercase tracking-wide text-gray-500 mb-1">From</label>
                        <input type="date" name="from" value="{{ $customFrom }}" class="rounded-lg border-gray-300 text-sm focus:border-green-700 focus:ring-green-700">
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold uppercase tracking-wide text-gray-500 mb-1">To</label>
                        <input type="date" name="to" value="{{ $customTo }}" class="rounded-lg border-gray-300 text-sm focus:border-green-700 focus:ring-green-700">
                    </div>
                    <button type="submit" class="px-4 py-2 rounded-lg text-sm font-semibold text-white shrink-0" style="background-color:#1a6b3c;">Apply</button>
                </div>
            </form>
        </div>

        {{-- Headline KPIs, with a vs.-previous-period indicator where we have one to compare against --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
            @php
                $deltaChip = function ($delta, $goodIsUp = true) {
                    if ($delta === null) return null;
                    $up = $delta > 0;
                    $flat = $delta == 0;
                    $good = $flat ? null : ($goodIsUp ? $up : ! $up);
                    $color = $flat ? 'text-gray-400 bg-gray-50' : ($good ? 'text-green-700 bg-green-50' : 'text-red-600 bg-red-50');
                    $arrow = $flat ? 'M5 12h14' : ($up ? 'M12 19V5m0 0l-6 6m6-6l6 6' : 'M12 5v14m0 0l-6-6m6 6l6-6');
                    return ['color' => $color, 'arrow' => $arrow, 'text' => ($flat ? 'No change' : ($up ? '+' : '').$delta.'%')];
                };
                $ticketsChip = $comparison ? $deltaChip($comparison['ticketsDelta'], false) : null;
                $rateChip = $comparison ? $deltaChip($comparison['rateDelta'], true) : null;
            @endphp

            <div class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Tickets created</p>
                <div class="flex items-end justify-between mt-1.5">
                    <p class="text-3xl font-semibold text-gray-800 tabular-nums">{{ number_format($totalTickets) }}</p>
                    @if($ticketsChip)
                        <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded text-[11px] font-semibold {{ $ticketsChip['color'] }}">
                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $ticketsChip['arrow'] }}" /></svg>
                            {{ $ticketsChip['text'] }}
                        </span>
                    @endif
                </div>
                <p class="mt-0.5 text-xs text-gray-400">{{ $comparison ? $comparison['label'] : $rangeLabel }}</p>
            </div>

            <div class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Resolution rate</p>
                <div class="flex items-end justify-between mt-1.5">
                    <p class="text-3xl font-semibold tabular-nums" style="color:#1a6b3c;">{{ $resolutionRate !== null ? $resolutionRate.'%' : '—' }}</p>
                    @if($rateChip)
                        <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded text-[11px] font-semibold {{ $rateChip['color'] }}">
                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $rateChip['arrow'] }}" /></svg>
                            {{ $rateChip['text'] }}
                        </span>
                    @endif
                </div>
                <p class="mt-0.5 text-xs text-gray-400">Resolved or closed</p>
            </div>

            <div class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Avg. resolution time</p>
                <p class="mt-1.5 text-3xl font-semibold text-gray-800 tabular-nums">
                    @if($avgResolutionHours === null)
                        —
                    @elseif($avgResolutionHours < 24)
                        {{ round($avgResolutionHours, 1) }}<span class="text-lg text-gray-400">h</span>
                    @else
                        {{ round($avgResolutionHours / 24, 1) }}<span class="text-lg text-gray-400">d</span>
                    @endif
                </p>
                <p class="mt-0.5 text-xs text-gray-400">Created → resolved</p>
            </div>

            <div class="rounded-xl border p-4 shadow-sm {{ $unassignedInRange > 0 ? 'border-amber-200 bg-amber-50/50' : 'border-gray-100 bg-white' }}">
                <p class="text-xs font-semibold uppercase tracking-wide {{ $unassignedInRange > 0 ? 'text-amber-700' : 'text-gray-500' }}">Unassigned</p>
                <p class="mt-1.5 text-3xl font-semibold tabular-nums {{ $unassignedInRange > 0 ? 'text-amber-700' : 'text-gray-800' }}">{{ number_format($unassignedInRange) }}</p>
                <p class="mt-0.5 text-xs {{ $unassignedInRange > 0 ? 'text-amber-700/70' : 'text-gray-400' }}">Still need an agent</p>
            </div>
        </div>

        {{-- Ticket volume trend --}}
        @if(count($trend) > 1)
            <div class="bg-white shadow-sm rounded-xl border border-gray-100 p-5 sm:p-6">
                <div class="flex items-center gap-2 mb-5">
                    <span class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0" style="background-color:#e8f3ec; color:#1a6b3c;">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125L7.5 8.25l4 4 5.5-6 4 4.5" /></svg>
                    </span>
                    <h3 class="text-sm font-semibold text-gray-700">Ticket volume over time</h3>
                </div>
                @php $maxV = max(1, collect($trend)->max('value')); @endphp
                <div class="flex items-end gap-[3px] h-32">
                    @foreach($trend as $point)
                        <div class="flex-1 min-w-0 group relative flex flex-col items-center justify-end h-full">
                            <div class="w-full rounded-sm transition-colors" style="height: {{ max(($point['value'] / $maxV) * 100, $point['value'] > 0 ? 4 : 1) }}%; background-color: {{ $point['value'] > 0 ? '#1a6b3c' : '#e5e7eb' }};"></div>
                            <div class="absolute bottom-full mb-1.5 hidden group-hover:block whitespace-nowrap bg-gray-800 text-white text-[10px] px-1.5 py-1 rounded z-10">{{ $point['label'] }}: {{ $point['value'] }}</div>
                        </div>
                    @endforeach
                </div>
                <div class="flex justify-between mt-2 text-[10px] text-gray-400">
                    <span>{{ $trend[0]['label'] }}</span>
                    <span>{{ end($trend)['label'] }}</span>
                </div>
            </div>
        @endif

        {{-- Ticket pie charts --}}
        <div>
            <div class="flex items-center gap-2 mb-3">
                <span class="w-1.5 h-5 rounded-full" style="background-color:#1a6b3c;"></span>
                <h3 class="text-base font-semibold text-gray-800">Tickets</h3>
                <span class="text-sm text-gray-400">· {{ $rangeLabel }}</span>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
                <div class="bg-white shadow-sm rounded-xl border border-gray-100 p-5">
                    <x-pie-chart :data="$byStatus" title="By status" />
                </div>
                <div class="bg-white shadow-sm rounded-xl border border-gray-100 p-5">
                    <x-pie-chart :data="$byPriority" title="By priority" />
                </div>
                <div class="bg-white shadow-sm rounded-xl border border-gray-100 p-5">
                    <x-pie-chart :data="$byCategory" title="By category" />
                </div>
            </div>
        </div>

        {{-- Agent workload --}}
        @if(count($byAgent) > 0)
            <div class="bg-white shadow-sm rounded-xl border border-gray-100 p-5 sm:p-6">
                <div class="flex items-center gap-2 mb-5">
                    <span class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0" style="background-color:#e8f3ec; color:#1a6b3c;">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" /></svg>
                    </span>
                    <h3 class="text-sm font-semibold text-gray-700">Tickets handled per agent</h3>
                </div>
                @php $maxA = max(1, collect($byAgent)->max()); @endphp
                <div class="space-y-3">
                    @foreach($byAgent as $agent => $count)
                        <div class="flex items-center gap-3">
                            <span class="w-32 shrink-0 text-sm text-gray-600 truncate">{{ $agent }}</span>
                            <div class="flex-1 h-2.5 rounded-full bg-gray-100 overflow-hidden">
                                <div class="h-full rounded-full" style="width: {{ ($count / $maxA) * 100 }}%; background-color:#1a6b3c;"></div>
                            </div>
                            <span class="w-8 shrink-0 text-sm font-medium text-gray-700 text-right tabular-nums">{{ $count }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Assets + Users pie charts --}}
        <div>
            <div class="flex items-center gap-2 mb-3">
                <span class="w-1.5 h-5 rounded-full bg-gray-300"></span>
                <h3 class="text-base font-semibold text-gray-800">Assets &amp; users</h3>
                <span class="text-sm text-gray-400">· Current snapshot</span>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
                <div class="bg-white shadow-sm rounded-xl border border-gray-100 p-5">
                    <x-pie-chart :data="$assetsByType" title="Assets by type" emptyText="No assets yet" />
                </div>
                <div class="bg-white shadow-sm rounded-xl border border-gray-100 p-5">
                    <x-pie-chart :data="[
                        ['label' => 'Assigned', 'value' => $assignedAssets, 'color' => '#1a6b3c', 'percent' => $totalAssets > 0 ? round($assignedAssets / $totalAssets * 100, 1) : 0],
                        ['label' => 'Unassigned', 'value' => $unassignedAssets, 'color' => '#f59e0b', 'percent' => $totalAssets > 0 ? round($unassignedAssets / $totalAssets * 100, 1) : 0],
                    ]" title="Assignment" emptyText="No assets yet" />
                </div>
                <div class="bg-white shadow-sm rounded-xl border border-gray-100 p-5">
                    <x-pie-chart :data="$assetsByStatus" title="Asset condition" emptyText="No assets yet" />
                </div>
                <div class="bg-white shadow-sm rounded-xl border border-gray-100 p-5">
                    <x-pie-chart :data="$usersByRole" title="Accounts by role" emptyText="No users yet" />
                </div>
            </div>
        </div>

        <p class="text-center text-xs text-gray-400 pt-2">Crest Forwarder Inc. — IT Service Desk · Internal use only</p>
    </div>
</x-app-layout>
