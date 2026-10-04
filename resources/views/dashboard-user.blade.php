<x-app-layout>
    <x-slot name="header">New Request</x-slot>

    @php
        $user   = auth()->user();
        $first  = \Illuminate\Support\Str::of($user->name)->explode(' ')->first();
        $active = $recent->first(fn ($t) => in_array($t->status, \App\Models\Ticket::ACTIVE));
        $steps  = ['Submitted', 'Assigned', 'In progress', 'Resolved'];
        $stage  = ['open' => 0, 'assigned' => 1, 'in_progress' => 2, 'on_hold' => 2];
        $cur    = $active ? ($stage[$active->status] ?? 0) : 0;
        $tiles  = [
            ['Open',        $counts['open'],        'text-blue-600'],
            ['In progress', $counts['in_progress'], 'text-amber-600'],
            ['Resolved',    $counts['resolved'],    'text-emerald-600'],
        ];
    @endphp

    <div class="mb-6">
        <p class="text-sm text-slate-500">{{ $user->company ?: 'JMS One IT Service Desk' }}</p>
        <h2 class="mt-0.5 text-2xl font-extrabold tracking-tight text-brand-800 sm:text-3xl">Hi {{ $first }}, how can we help today?</h2>
        <p class="mt-1.5 max-w-2xl text-sm text-slate-600">Report your IT problem here instead of calling or messaging. Every request is logged, assigned to an engineer, and updated where you can see it.</p>
    </div>

    {{-- Where your current request stands --}}
    @if ($active)
        <a href="{{ route('tickets.show', $active) }}" class="group mb-6 block rounded-2xl bg-brand-900 p-5 text-white shadow-sm transition hover:bg-brand-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-400 focus-visible:ring-offset-2 sm:p-6">
            <div class="flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between sm:gap-6">
                <div class="min-w-0">
                    <p class="text-sm text-brand-200">Your open request</p>
                    <p class="mt-0.5 truncate text-lg font-bold">{{ $active->subject }}</p>
                    <p class="text-sm text-brand-200">
                        {{ $active->ticket_no }} &middot; sent {{ $active->created_at->diffForHumans() }}
                        @if ($active->assignee) &middot; handled by {{ $active->assignee->name }} @endif
                        @if ($active->scheduledShort()) &middot; scheduled {{ $active->scheduledShort() }} @endif
                    </p>
                </div>
                <span class="shrink-0 text-sm font-semibold text-white underline-offset-4 group-hover:underline">View ticket</span>
            </div>

            <ol class="mt-5 grid grid-cols-4 gap-2" aria-label="Progress">
                @foreach ($steps as $i => $name)
                    <li @if ($i === $cur) aria-current="step" @endif>
                        <div class="h-1.5 rounded-full {{ $i <= $cur ? 'bg-brand-400' : 'bg-white/15' }}"></div>
                        <p class="mt-2 text-xs sm:text-sm {{ $i === $cur ? 'font-semibold text-white' : ($i < $cur ? 'text-brand-100' : 'text-brand-200/60') }}">{{ $name }}</p>
                    </li>
                @endforeach
            </ol>
            @if ($active->status === 'on_hold')
                <p class="mt-3 text-sm text-amber-300">Paused for now. Open the ticket to see why.</p>
            @endif
        </a>
    @endif

    <div class="grid gap-6 xl:grid-cols-3">
        {{-- Form --}}
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-8 xl:col-span-2">
            <div class="mb-6">
                <h3 class="text-lg font-bold text-brand-800">Submit a new ticket</h3>
                <p class="text-sm text-slate-500">It takes about a minute. The more detail you give, the faster we can fix it.</p>
            </div>
            @include('tickets._form')
        </section>

        {{-- Side panel --}}
        <aside class="space-y-6">
            <div class="grid grid-cols-3 divide-x divide-slate-100 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                @foreach ($tiles as [$label, $value, $color])
                    <a href="{{ route('tickets.index') }}" class="px-4 py-3.5 transition hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-brand-500">
                        <p class="text-2xl font-extrabold {{ $color }}">{{ $value }}</p>
                        <p class="text-xs text-slate-500">{{ $label }}</p>
                    </a>
                @endforeach
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                    <h3 class="font-semibold text-brand-800">My recent tickets</h3>
                    <a href="{{ route('tickets.index') }}" class="text-sm font-medium text-brand-600 hover:underline">View all</a>
                </div>
                <div class="divide-y divide-slate-100">
                    @forelse ($recent as $t)
                        <a href="{{ route('tickets.show', $t) }}" class="block px-5 py-3.5 transition hover:bg-slate-50 focus:outline-none focus-visible:bg-slate-50">
                            <div class="flex items-start justify-between gap-3">
                                <p class="line-clamp-1 text-sm font-medium text-slate-800">{{ $t->subject }}</p>
                                <span class="shrink-0 rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset {{ $t->statusClasses() }}">{{ $t->statusLabel() }}</span>
                            </div>
                            <p class="mt-0.5 text-xs text-slate-500">{{ $t->ticket_no }} &middot; {{ $t->created_at->diffForHumans() }}</p>
                        </a>
                    @empty
                        <div class="px-5 py-8 text-center">
                            <p class="text-sm font-medium text-slate-700">No tickets yet</p>
                            <p class="mt-1 text-xs text-slate-500">Fill in the form and your first ticket will appear here.</p>
                        </div>
                    @endforelse
                </div>
            </div>

            <details class="group rounded-2xl border border-brand-100 bg-brand-50">
                <summary class="flex cursor-pointer list-none items-center justify-between px-5 py-4 font-semibold text-brand-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 [&::-webkit-details-marker]:hidden">
                    Tips for a faster fix
                    <svg class="h-4 w-4 text-brand-600 transition group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                </summary>
                <ul class="space-y-2 px-5 pb-5 text-sm text-slate-600">
                    <li>Copy the exact error message if you see one.</li>
                    <li>Say what you were doing when it happened.</li>
                    <li>Choose <strong>Critical</strong> only when many people or a key system are down.</li>
                    <li>Add a contact number so we can reach you quickly.</li>
                </ul>
            </details>
        </aside>
    </div>
</x-app-layout>
