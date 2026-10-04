<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 30px 36px 46px; }
        body { font-family: 'Helvetica', Arial, sans-serif; color: #1f2937; font-size: 10px; }

        /* ---------- Cover band ---------- */
        table.cover { width: 100%; border-collapse: collapse; background-color: #123f24; border-radius: 0; margin-bottom: 18px; }
        table.cover td { padding: 18px 20px; vertical-align: middle; }
        .cover-eyebrow { color: #bfe6cc; font-size: 8.5px; text-transform: uppercase; letter-spacing: 0.08em; font-weight: bold; margin: 0; }
        .cover-title { color: #ffffff; font-size: 20px; font-weight: bold; margin: 4px 0 0; }
        .cover-meta { color: #d7f0dd; font-size: 9.5px; margin-top: 5px; }
        .cover-logo-cell { width: 56px; text-align: right; }
        .cover-logo { width: 42px; height: 42px; }

        /* ---------- Executive summary ---------- */
        .summary-box { background-color: #f9fafb; border: 1px solid #e5e7eb; border-left: 3px solid #1a6b3c; border-radius: 4px; padding: 11px 14px; margin-bottom: 16px; }
        .summary-label { font-size: 8px; text-transform: uppercase; letter-spacing: 0.06em; color: #1a6b3c; font-weight: bold; margin: 0 0 4px; }
        .summary-text { font-size: 10px; line-height: 1.5; color: #374151; margin: 0; }

        /* ---------- KPI cards ---------- */
        table.cards { width: 100%; border-collapse: separate; border-spacing: 7px 0; margin: 0 -7px 18px; }
        table.cards td { width: 25%; border: 1px solid #e5e7eb; border-radius: 6px; padding: 10px 12px; background-color: #ffffff; vertical-align: top; }
        table.cards td.warn { background-color: #fffbeb; border-color: #fde68a; }
        .card-label { font-size: 8px; text-transform: uppercase; letter-spacing: 0.04em; color: #6b7280; font-weight: bold; }
        .card-value { font-size: 19px; font-weight: bold; color: #123f24; margin-top: 3px; }
        .warn .card-label, .warn .card-value { color: #b45309; }
        .card-delta { font-size: 8px; font-weight: bold; margin-top: 3px; }
        .delta-up-good { color: #15803d; }
        .delta-up-bad { color: #dc2626; }
        .delta-down-good { color: #15803d; }
        .delta-down-bad { color: #dc2626; }
        .delta-flat { color: #9ca3af; }

        /* ---------- Section headers ---------- */
        .section-title { font-size: 12px; font-weight: bold; color: #123f24; margin: 4px 0 9px; padding-left: 8px; border-left: 3px solid #1a6b3c; }
        .section-sub { font-size: 12px; font-weight: bold; color: #4b5563; margin: 4px 0 9px; padding-left: 8px; border-left: 3px solid #d1d5db; }

        /* ---------- Chart grid ---------- */
        table.chart-grid { width: 100%; border-collapse: separate; border-spacing: 7px; margin: 0 -7px 4px; }
        table.chart-grid td { width: 33.33%; border: 1px solid #e5e7eb; border-radius: 6px; padding: 11px; vertical-align: top; background: #fff; }
        .chart-title { font-size: 9px; font-weight: bold; color: #374151; margin-bottom: 7px; text-align: center; text-transform: uppercase; letter-spacing: 0.03em; }

        table.legend { width: 100%; margin-top: 8px; }
        table.legend td { padding: 2px 0; font-size: 8.5px; }
        .swatch { display: inline-block; width: 7px; height: 7px; border-radius: 50%; margin-right: 4px; }
        .legend-val { text-align: right; color: #6b7280; }

        /* ---------- Tables ---------- */
        table.grid { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        table.grid thead th { background-color: #1a6b3c; color: #fff; text-align: left; padding: 6px 9px; font-size: 8.5px; text-transform: uppercase; letter-spacing: 0.02em; }
        table.grid tbody td { padding: 6px 9px; border-bottom: 1px solid #e5e7eb; font-size: 9px; }
        table.grid tbody tr:nth-child(even) { background-color: #f9fafb; }
        .num { text-align: right; }

        .page-break { page-break-before: always; }

        /* ---------- Footer ---------- */
        .footer { position: fixed; left: 0; right: 0; bottom: -34px; border-top: 1px solid #e5e7eb; padding-top: 6px; font-size: 8px; color: #9ca3af; }
        .footer table { width: 100%; }
    </style>
</head>
<body>
    <div class="footer">
        <table>
            <tr>
                <td>Crest Forwarder Inc. — IT Service Desk · Confidential, internal use only</td>
                <td style="text-align:right;">Prepared by {{ $preparedBy }} · {{ $generatedAt->format('M j, Y g:i A') }}</td>
            </tr>
        </table>
    </div>

    {{-- Cover band --}}
    <table class="cover">
        <tr>
            <td>
                <p class="cover-eyebrow">Crest Forwarder Inc. &middot; IT Service Desk</p>
                <p class="cover-title">Management Report</p>
                <p class="cover-meta">{{ $rangeLabel }} &middot; Generated {{ $generatedAt->format('F j, Y') }} &middot; Prepared by {{ $preparedBy }}</p>
            </td>
            @if($logoData)
                <td class="cover-logo-cell"><img src="{{ $logoData }}" class="cover-logo" /></td>
            @endif
        </tr>
    </table>

    {{-- Executive summary --}}
    <div class="summary-box">
        <p class="summary-label">Executive summary</p>
        <p class="summary-text">{{ $summary }}</p>
    </div>

    {{-- KPI cards --}}
    @php
        function pdf_delta_cell($delta, $goodIsUp = true) {
            if ($delta === null) return '<span class="delta-flat">No prior-period data</span>';
            $up = $delta > 0;
            $flat = $delta == 0;
            if ($flat) return '<span class="delta-flat">No change vs. previous period</span>';
            $good = $goodIsUp ? $up : ! $up;
            $class = $good ? ($up ? 'delta-up-good' : 'delta-down-good') : ($up ? 'delta-up-bad' : 'delta-down-bad');
            $arrow = $up ? '&#9650;' : '&#9660;';
            $sign = $up ? '+' : '';
            return "<span class=\"{$class}\">{$arrow} {$sign}{$delta}% vs. previous period</span>";
        }
    @endphp
    <table class="cards">
        <tr>
            <td>
                <div class="card-label">Tickets created</div>
                <div class="card-value">{{ number_format($totalTickets) }}</div>
                <div class="card-delta">{!! $comparison ? pdf_delta_cell($comparison['ticketsDelta'], false) : '<span class="delta-flat">'.e($rangeLabel).'</span>' !!}</div>
            </td>
            <td>
                <div class="card-label">Resolution rate</div>
                <div class="card-value">{{ $resolutionRate !== null ? $resolutionRate.'%' : '—' }}</div>
                <div class="card-delta">{!! $comparison ? pdf_delta_cell($comparison['rateDelta'], true) : '<span class="delta-flat">Resolved or closed</span>' !!}</div>
            </td>
            <td>
                <div class="card-label">Avg. resolution time</div>
                <div class="card-value">
                    @if($avgResolutionHours === null) — @elseif($avgResolutionHours < 24) {{ round($avgResolutionHours, 1) }}h @else {{ round($avgResolutionHours / 24, 1) }}d @endif
                </div>
                <div class="card-delta"><span class="delta-flat">Created &rarr; resolved</span></div>
            </td>
            <td class="{{ $unassignedInRange > 0 ? 'warn' : '' }}">
                <div class="card-label">Unassigned</div>
                <div class="card-value">{{ number_format($unassignedInRange) }}</div>
                <div class="card-delta"><span class="{{ $unassignedInRange > 0 ? 'delta-up-bad' : 'delta-flat' }}">{{ $unassignedInRange > 0 ? 'Needs attention' : 'All assigned' }}</span></div>
            </td>
        </tr>
    </table>

    @php
        // A simple pie built as SVG for the PDF — dompdf handles basic
        // <path> arcs reliably, which is safer here than stroke-dasharray.
        function pdf_pie_paths(array $data, float $r = 40): string {
            $total = array_sum(array_column($data, 'value'));
            if ($total <= 0) return '';
            $cx = $r; $cy = $r; $angle = -90; $out = '';
            foreach ($data as $slice) {
                $frac = $slice['value'] / $total;
                $sweep = $frac * 360;
                $x1 = $cx + $r * cos(deg2rad($angle));
                $y1 = $cy + $r * sin(deg2rad($angle));
                $endAngle = $angle + $sweep;
                $x2 = $cx + $r * cos(deg2rad($endAngle));
                $y2 = $cy + $r * sin(deg2rad($endAngle));
                $large = $sweep > 180 ? 1 : 0;
                if ($frac >= 0.999) {
                    $out .= "<circle cx=\"{$cx}\" cy=\"{$cy}\" r=\"{$r}\" fill=\"{$slice['color']}\" />";
                } else {
                    $out .= "<path d=\"M{$cx},{$cy} L{$x1},{$y1} A{$r},{$r} 0 {$large} 1 {$x2},{$y2} Z\" fill=\"{$slice['color']}\" />";
                }
                $angle = $endAngle;
            }
            return $out;
        }

        $pieBlock = function (string $title, array $data, string $emptyText = 'No data') {
            $html = '<div class="chart-title">'.e($title).'</div>';
            if (count($data) > 0) {
                $html .= '<svg width="80" height="80" viewBox="0 0 80 80" style="display:block;margin:0 auto;">'.pdf_pie_paths($data, 40).'</svg>';
                $html .= '<table class="legend">';
                foreach ($data as $slice) {
                    $html .= '<tr><td><span class="swatch" style="background-color:'.$slice['color'].';"></span>'.e($slice['label']).'</td><td class="legend-val">'.$slice['value'].' ('.$slice['percent'].'%)</td></tr>';
                }
                $html .= '</table>';
            } else {
                $html .= '<p style="text-align:center;color:#9ca3af;font-size:9px;margin-top:22px;">'.e($emptyText).'</p>';
            }
            return $html;
        };
    @endphp

    <p class="section-title">Tickets &mdash; {{ $rangeLabel }}</p>
    <table class="chart-grid">
        <tr>
            <td>{!! $pieBlock('By status', $byStatus) !!}</td>
            <td>{!! $pieBlock('By priority', $byPriority) !!}</td>
            <td>{!! $pieBlock('By category', $byCategory) !!}</td>
        </tr>
    </table>

    @if(count($byAgent) > 0)
        <p class="section-sub">Tickets handled per agent</p>
        <table class="grid">
            <thead><tr><th>Agent</th><th class="num">Tickets</th><th class="num">Share</th></tr></thead>
            <tbody>
                @php $agentTotal = max(1, array_sum($byAgent->all())); @endphp
                @foreach($byAgent as $agent => $count)
                    <tr><td>{{ $agent }}</td><td class="num">{{ $count }}</td><td class="num">{{ round($count / $agentTotal * 100) }}%</td></tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="page-break"></div>

    <p class="section-title" style="margin-top:0;">Assets &amp; Accounts &mdash; Current Snapshot</p>
    <table class="chart-grid">
        <tr>
            <td>{!! $pieBlock('Assets by type', $assetsByType, 'No assets yet') !!}</td>
            <td>
                @php
                    $assignPie = [
                        ['label' => 'Assigned', 'value' => $assignedAssets, 'color' => '#1a6b3c', 'percent' => $totalAssets > 0 ? round($assignedAssets / $totalAssets * 100, 1) : 0],
                        ['label' => 'Unassigned', 'value' => $unassignedAssets, 'color' => '#f59e0b', 'percent' => $totalAssets > 0 ? round($unassignedAssets / $totalAssets * 100, 1) : 0],
                    ];
                @endphp
                {!! $pieBlock('Assignment', $totalAssets > 0 ? $assignPie : [], 'No assets yet') !!}
            </td>
            <td>{!! $pieBlock('Asset condition', $assetsByStatus, 'No assets yet') !!}</td>
        </tr>
    </table>

    <p class="section-sub">Accounts by role</p>
    <table class="chart-grid">
        <tr>
            <td style="width:33.33%;">{!! $pieBlock('Accounts by role', $usersByRole, 'No users yet') !!}</td>
            <td style="width:66.66%; border:none; background:none;">
                <table class="grid" style="margin-bottom:0;">
                    <thead><tr><th>Role</th><th class="num">Accounts</th><th class="num">Share</th></tr></thead>
                    <tbody>
                        @foreach($usersByRole as $row)
                            <tr><td>{{ $row['label'] }}</td><td class="num">{{ $row['value'] }}</td><td class="num">{{ $row['percent'] }}%</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
