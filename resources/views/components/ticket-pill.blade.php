@props(['ticket', 'type' => 'status'])

@php
    $dots = [
        'open' => 'bg-blue-500', 'assigned' => 'bg-indigo-500', 'in_progress' => 'bg-amber-500', 'on_hold' => 'bg-slate-400',
        'resolved' => 'bg-emerald-500', 'closed' => 'bg-slate-400', 'cancelled' => 'bg-rose-500',
        'low' => 'bg-slate-400', 'medium' => 'bg-sky-500', 'high' => 'bg-orange-500', 'critical' => 'bg-red-500',
    ];
    $isStatus = $type === 'status';
    $value    = $isStatus ? $ticket->status : $ticket->priority;
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset ' . ($isStatus ? $ticket->statusClasses() : $ticket->priorityClasses())]) }}>
    <span class="h-1.5 w-1.5 shrink-0 rounded-full {{ $dots[$value] ?? 'bg-slate-400' }}"></span>
    {{ $isStatus ? $ticket->statusLabel() : $ticket->priorityLabel() }}
</span>
