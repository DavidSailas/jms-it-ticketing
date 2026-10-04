{{-- Admin / Super Admin: the next booked visits and calls. --}}
<section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm" aria-labelledby="upcoming-title">
    <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-4 py-3">
        <div>
            <h3 id="upcoming-title" class="font-semibold text-brand-800">Upcoming bookings</h3>
            <p class="text-xs text-slate-500">Visits and calls requesters booked</p>
        </div>
        <a href="{{ route('schedule.index') }}" class="shrink-0 text-xs font-medium text-brand-600 hover:underline">Open schedule</a>
    </div>

    @forelse ($upcoming as $t)
        @php
            $late = $t->scheduled_for->isPast();
            $day  = $t->scheduled_for->isToday() ? 'Today' : ($t->scheduled_for->isTomorrow() ? 'Tomorrow' : $t->scheduled_for->format('M j'));
        @endphp
        <a href="{{ route('tickets.show', $t) }}" class="group flex items-center gap-3 border-b border-slate-100 px-4 py-2.5 transition last:border-0 hover:bg-brand-50/60 focus:outline-none focus-visible:bg-brand-50/60">
            <div class="w-[4.5rem] shrink-0 rounded-lg px-1 py-1.5 text-center ring-1 ring-inset {{ $late ? 'bg-red-50 ring-red-200' : 'bg-slate-50 ring-slate-200' }}">
                <p class="text-[10px] font-semibold uppercase tracking-wide {{ $late ? 'text-red-600' : 'text-slate-500' }}">{{ $day }}</p>
                <p class="text-[13px] font-bold text-slate-800">{{ $t->scheduled_for->format('g:i A') }}</p>
            </div>
            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-medium text-slate-900 group-hover:text-brand-700 group-hover:underline">{{ $t->subject }}</p>
                <p class="truncate text-xs text-slate-500">
                    {{ $t->user->name }} &middot;
                    @if ($t->assignee) {{ $t->assignee->name }} @else <span class="font-medium text-violet-600">Unassigned</span> @endif
                    @if ($t->supportTypeLabel()) &middot; {{ $t->supportTypeLabel() }} @endif
                </p>
            </div>
            <svg class="h-4 w-4 shrink-0 text-slate-300 transition group-hover:translate-x-0.5 group-hover:text-brand-600" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m9 5 7 7-7 7"/></svg>
        </a>
    @empty
        <div class="px-4 py-6 text-center">
            <svg class="mx-auto h-9 w-9 text-slate-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4.5" width="18" height="16.5" rx="2"/><path d="M3 9.5h18M8 2.5v4M16 2.5v4"/></svg>
            <p class="mt-3 text-sm font-medium text-slate-700">Nothing booked</p>
            <p class="text-sm text-slate-500">New bookings will show up here.</p>
        </div>
    @endforelse
</section>
