<x-app-layout>
    <x-slot name="header">My Assignments</x-slot>

    @php
        $user    = auth()->user();
        $first   = \Illuminate\Support\Str::of($user->name)->explode(' ')->first();
        $count   = $queue->count();
        $overdue = $queue->filter->isOverdue()->count();
        $next    = $queue->first();
        $rest    = $queue->slice(1)->values();
        $edge    = ['critical' => 'border-l-red-500', 'high' => 'border-l-orange-500', 'medium' => 'border-l-sky-500', 'low' => 'border-l-slate-300'];
        $chips   = collect(['assigned' => 'To start', 'in_progress' => 'In progress', 'on_hold' => 'On hold'])
                    ->map(fn ($label, $s) => [$label, $rest->where('status', $s)->count()])->filter(fn ($c) => $c[1] > 0);
        $cards   = [
            ['To start',          $stats['todo'],          'text-indigo-600',  'assigned'],
            ['In progress',       $stats['in_progress'],   'text-amber-600',   'in_progress'],
            ['On hold',           $stats['on_hold'],       'text-slate-600',   'on_hold'],
            ['Resolved this week', $stats['resolved_week'], 'text-emerald-600', 'resolved'],
        ];
    @endphp

    <div class="mb-5">
        <h2 class="text-2xl font-extrabold tracking-tight text-brand-800">Hi {{ $first }}, here is your work</h2>
        <p class="mt-0.5 text-sm text-slate-500">
            @if ($count === 0)
                Nothing is assigned to you right now.
            @else
                {{ $count }} active {{ \Illuminate\Support\Str::plural('ticket', $count) }}@if ($overdue), <span class="font-semibold text-red-600">{{ $overdue }} past {{ $overdue === 1 ? 'its' : 'their' }} target</span>@endif. Most urgent first.
            @endif
        </p>
    </div>

    {{-- One strip of numbers; each links to the filtered ticket list --}}
    <div class="mb-6 grid grid-cols-2 gap-px overflow-hidden rounded-2xl border border-slate-200 bg-slate-200 shadow-sm lg:grid-cols-4">
        @foreach ($cards as [$label, $value, $color, $status])
            <a href="{{ route('tickets.index', ['status' => $status]) }}" class="bg-white px-5 py-4 transition hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-brand-500">
                <p class="text-sm text-slate-500">{{ $label }}</p>
                <p class="mt-1 text-3xl font-extrabold {{ $color }}">{{ $value }}</p>
            </a>
        @endforeach
    </div>

    @if (! $next)
        <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-16 text-center">
            <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-emerald-50 text-emerald-600">
                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7.5"/></svg>
            </span>
            <p class="mt-4 font-semibold text-slate-800">You are all caught up</p>
            <p class="mt-1 text-sm text-slate-500">New tickets show up here as soon as an admin assigns them to you.</p>
        </div>
    @else
        {{-- Work on this next --}}
        <section aria-labelledby="next-heading" class="mb-6 rounded-2xl border border-l-4 border-slate-200 {{ $edge[$next->priority] ?? 'border-l-slate-300' }} bg-white p-5 shadow-sm sm:p-6">
            <p id="next-heading" class="text-sm font-semibold text-brand-600">Work on this next</p>
            <div class="mt-2 flex flex-wrap items-center gap-2">@include('dashboard.partials.ticket-badges', ['t' => $next])</div>
            <a href="{{ route('tickets.show', $next) }}" class="mt-3 block text-xl font-bold text-brand-800 hover:underline">{{ $next->subject }}</a>
            <p class="text-sm text-slate-500">{{ $next->ticket_no }} &middot; {{ $next->category }} &middot; opened {{ $next->created_at->diffForHumans() }}</p>

            <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-3">
                <div><dt class="text-slate-500">Requested by</dt><dd class="mt-0.5 font-medium text-slate-800">{{ $next->user->name }}{{ $next->user->company ? ', ' . $next->user->company : '' }}</dd></div>
                <div><dt class="text-slate-500">Location</dt><dd class="mt-0.5 font-medium text-slate-800">{{ $next->location ?: 'Not given' }}</dd></div>
                <div><dt class="text-slate-500">Contact</dt><dd class="mt-0.5 font-medium text-slate-800">{{ $next->contact_phone ?: 'Not given' }}</dd></div>
            </dl>
            @if ($next->description)
                <p class="mt-4 line-clamp-2 max-w-3xl text-sm leading-relaxed text-slate-600">{{ $next->description }}</p>
            @endif

            <div class="mt-5 flex flex-wrap gap-2 border-t border-slate-100 pt-4">@include('dashboard.partials.ticket-actions', ['t' => $next])</div>
        </section>

        {{-- The rest of the queue --}}
        @if ($rest->isNotEmpty())
            <section x-data="{ f: 'all' }" aria-labelledby="queue-heading">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <h3 id="queue-heading" class="font-semibold text-brand-800">Rest of your queue</h3>
                    @if ($chips->count() > 1)
                        <div class="flex flex-wrap gap-1.5" role="group" aria-label="Filter by status">
                            <button type="button" @click="f = 'all'" :aria-pressed="f === 'all'" :class="f === 'all' ? 'bg-brand-800 text-white' : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50'" class="rounded-full px-3 py-1 text-xs font-medium transition">All {{ $rest->count() }}</button>
                            @foreach ($chips as $s => [$label, $n])
                                <button type="button" @click="f = '{{ $s }}'" :aria-pressed="f === '{{ $s }}'" :class="f === '{{ $s }}' ? 'bg-brand-800 text-white' : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50'" class="rounded-full px-3 py-1 text-xs font-medium transition">{{ $label }} {{ $n }}</button>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="divide-y divide-slate-100 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    @foreach ($rest as $t)
                        <article x-show="f === 'all' || f === '{{ $t->status }}'" class="flex flex-col gap-3 border-l-4 {{ $edge[$t->priority] ?? 'border-l-slate-300' }} px-4 py-4 sm:px-5 lg:flex-row lg:items-center lg:gap-6">
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">@include('dashboard.partials.ticket-badges', ['t' => $t])</div>
                                <a href="{{ route('tickets.show', $t) }}" class="mt-1.5 block truncate font-semibold text-brand-800 hover:underline">{{ $t->subject }}</a>
                                <p class="truncate text-sm text-slate-500">{{ $t->ticket_no }} &middot; {{ $t->user->name }}{{ $t->user->company ? ', ' . $t->user->company : '' }}@if ($t->location) &middot; {{ $t->location }}@endif</p>
                            </div>
                            <div class="flex shrink-0 flex-wrap gap-2">@include('dashboard.partials.ticket-actions', ['t' => $t])</div>
                        </article>
                    @endforeach
                </div>
            </section>
        @endif
    @endif
</x-app-layout>
