{{-- Priority, status, support type, schedule and service-level badges for one ticket. Pass $t. --}}
@php($sla = $t->slaBadge())
<span class="rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset {{ $t->priorityClasses() }}">{{ $t->priorityLabel() }}</span>
<span class="rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset {{ $t->statusClasses() }}">{{ $t->statusLabel() }}</span>
@if ($t->supportTypeLabel())
    <span class="rounded-full bg-brand-50 px-2.5 py-0.5 text-xs font-medium text-brand-700">{{ $t->supportTypeLabel() }}</span>
@else
    <span class="rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-medium text-amber-700">Decide: remote or on-site</span>
@endif
@if ($t->scheduledShort())
    <span class="rounded-full bg-indigo-50 px-2.5 py-0.5 text-xs font-medium text-indigo-700">Scheduled {{ $t->scheduledShort() }}</span>
@endif
@if ($sla)
    <span class="text-xs {{ $sla[1] }}">{{ $sla[0] }}</span>
@endif
