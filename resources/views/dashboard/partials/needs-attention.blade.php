{{-- Admin / Super Admin: tickets that have passed their service-level target. --}}
<section class="overflow-hidden rounded-xl border border-rose-200 bg-white shadow-sm" aria-labelledby="attention-title">
    <div class="flex items-center justify-between gap-3 border-b border-rose-100 bg-rose-50/70 px-4 py-3">
        <div class="flex items-center gap-3">
            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-rose-100 text-rose-600">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="13" r="8"/><path d="M12 9v4l2 2M9 3h6"/></svg>
            </span>
            <div>
                <h3 id="attention-title" class="font-semibold text-rose-900">
                    Needs attention
                    <span class="ml-1.5 rounded-full bg-rose-600 px-2 py-0.5 align-middle text-xs font-bold leading-none text-white">{{ $overdueTickets->count() }}</span>
                </h3>
                <p class="text-xs text-rose-700">Past their service-level target, longest overdue first</p>
            </div>
        </div>
    </div>

    <ul class="divide-y divide-slate-100">
        @foreach ($overdueTickets->take(5) as $t)
            @php($sla = $t->slaBadge())
            <li>
                <a href="{{ route('tickets.show', $t) }}" class="group flex items-center gap-3 px-4 py-2.5 transition hover:bg-rose-50/40 focus:outline-none focus-visible:bg-rose-50/60">
                    <x-ticket-pill :ticket="$t" type="priority" />
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium text-slate-900 group-hover:text-brand-700 group-hover:underline">{{ $t->subject }}</p>
                        <p class="truncate text-xs text-slate-500">
                            {{ $t->ticket_no }} &middot; {{ $t->user->name }} &middot;
                            @if ($t->assignee) {{ $t->assignee->name }} @else <span class="font-medium text-violet-600">Unassigned</span> @endif
                        </p>
                    </div>
                    <span class="shrink-0 text-xs {{ $sla ? $sla[1] : 'text-red-600 font-semibold' }}">{{ $sla ? $sla[0] : 'Overdue' }}</span>
                </a>
            </li>
        @endforeach
    </ul>

    @if ($overdueTickets->count() > 5)
        <div class="border-t border-slate-100 bg-slate-50/50 px-4 py-2.5 text-sm text-slate-500">
            Showing the 5 most overdue of <span class="font-semibold text-slate-700">{{ $overdueTickets->count() }}</span>.
            <a href="{{ route('tickets.index') }}" class="font-medium text-brand-600 hover:underline">View all tickets</a>
        </div>
    @endif
</section>
