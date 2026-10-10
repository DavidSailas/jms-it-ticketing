@props(['ticket', 'assignable' => false])
@php
    $t = $ticket;
    $accent = ['low' => 'border-l-slate-300', 'medium' => 'border-l-sky-400', 'high' => 'border-l-orange-500', 'critical' => 'border-l-red-600'];
    $canAssign = $assignable && ! $t->isFinished();
    $sla = in_array($t->status, \App\Models\Ticket::ACTIVE) ? $t->slaBadge() : null;
@endphp
<div class="rounded-xl border border-l-4 border-slate-200 {{ $accent[$t->priority] ?? 'border-l-slate-300' }} bg-white shadow-sm transition hover:border-brand-300 hover:shadow-md">
<a href="{{ route('tickets.show', $t) }}"
   class="group flex flex-col rounded-t-xl p-3.5 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500">
    <div class="flex items-center justify-between gap-2">
        <span class="truncate text-xs font-semibold text-brand-700">{{ $t->ticket_no }}</span>
        <x-ticket-pill :ticket="$t" type="priority" />
    </div>
    <p class="mt-2 line-clamp-2 font-semibold leading-snug text-slate-900 group-hover:text-brand-700">{{ $t->subject }}</p>
    <p class="mt-1 flex items-center gap-1.5 text-xs text-slate-500"><span class="truncate">{{ $t->category }}</span><x-jms-requested-badge :ticket="$t" /></p>
    <div class="mt-3 flex items-center gap-2 border-t border-slate-100 pt-3">
        <x-avatar :user="$t->user" size="h-7 w-7" text="text-[10px]" />
        <div class="min-w-0 flex-1">
            <p class="flex items-center gap-1.5 text-xs font-medium text-slate-700"><span class="truncate">{{ $t->user->name }}</span><x-admin-badge :user="$t->user" /></p>
            <p class="truncate text-[11px] text-slate-400">{{ $t->assignee ? 'Engineer: ' . $t->assignee->name : 'Not assigned yet' }}</p>
        </div>
        <div class="shrink-0 text-right text-[11px]">
            @if ($sla) <p class="font-medium {{ $sla[1] }}">{{ $sla[0] }}</p> @endif
            <p class="text-slate-400" title="{{ $t->created_at->format('M d, Y h:i A') }}">{{ $t->created_at->diffForHumans(short: true) }}</p>
        </div>
    </div>
</a>
@if ($canAssign)
    <div class="border-t border-slate-100 px-3.5 py-2">
        <button type="button" @click="openAssign(@js(\App\Support\AssignPayload::for($t)))"
                class="inline-flex w-full items-center justify-center gap-1.5 rounded-lg px-2 py-1.5 text-xs font-semibold {{ $t->assigned_to ? 'text-slate-600 hover:bg-slate-100' : 'bg-violet-600 text-white hover:bg-violet-700' }} focus:outline-none focus-visible:ring-2 focus-visible:ring-violet-500">
            {{ $t->assigned_to ? 'Reassign' : 'Accept & assign' }}
        </button>
    </div>
@endif
</div>
