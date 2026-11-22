<x-app-layout>
    <x-slot name="header">My Calendar</x-slot>

    @php
        $prev     = $month->copy()->subMonth()->format('Y-m');
        $nextM    = $month->copy()->addMonth()->format('Y-m');
        $weekdays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        $days     = collect(\Carbon\CarbonPeriod::create($gridStart, $gridEnd));
        $firstName = explode(' ', trim(auth()->user()->name))[0];

        // Booking colours follow the status colours used across the app.
        $chip = [
            'open'        => 'bg-blue-50 text-blue-800 ring-blue-200 border-l-blue-500',
            'assigned'    => 'bg-indigo-50 text-indigo-800 ring-indigo-200 border-l-indigo-500',
            'in_progress' => 'bg-amber-50 text-amber-800 ring-amber-200 border-l-amber-500',
            'on_hold'     => 'bg-slate-100 text-slate-700 ring-slate-200 border-l-slate-400',
            'resolved'    => 'bg-emerald-50 text-emerald-800 ring-emerald-200 border-l-emerald-500',
            'closed'      => 'bg-slate-100 text-slate-500 ring-slate-200 border-l-slate-400',
        ];
        $dot = [
            'open' => 'bg-blue-500', 'assigned' => 'bg-indigo-500', 'in_progress' => 'bg-amber-500',
            'on_hold' => 'bg-slate-400', 'resolved' => 'bg-emerald-500', 'closed' => 'bg-slate-400',
        ];
        // Sticky-note colours for personal notes.
        $tone = [
            'amber'   => ['chip' => 'bg-amber-50 text-amber-900 ring-amber-200',     'dot' => 'bg-amber-400',   'card' => 'bg-amber-50 ring-amber-200',     'pick' => 'bg-amber-400'],
            'blue'    => ['chip' => 'bg-sky-50 text-sky-900 ring-sky-200',           'dot' => 'bg-sky-400',     'card' => 'bg-sky-50 ring-sky-200',         'pick' => 'bg-sky-400'],
            'emerald' => ['chip' => 'bg-emerald-50 text-emerald-900 ring-emerald-200', 'dot' => 'bg-emerald-400', 'card' => 'bg-emerald-50 ring-emerald-200', 'pick' => 'bg-emerald-400'],
            'rose'    => ['chip' => 'bg-rose-50 text-rose-900 ring-rose-200',        'dot' => 'bg-rose-400',    'card' => 'bg-rose-50 ring-rose-200',       'pick' => 'bg-rose-400'],
            'violet'  => ['chip' => 'bg-violet-50 text-violet-900 ring-violet-200',  'dot' => 'bg-violet-400',  'card' => 'bg-violet-50 ring-violet-200',   'pick' => 'bg-violet-400'],
            'slate'   => ['chip' => 'bg-slate-100 text-slate-800 ring-slate-200',    'dot' => 'bg-slate-400',   'card' => 'bg-slate-50 ring-slate-200',     'pick' => 'bg-slate-400'],
        ];
        $field = 'w-full rounded-lg border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500';
    @endphp

    <div class="mb-6">
        <h2 class="text-2xl font-extrabold tracking-tight text-brand-800">Your calendar</h2>
        <p class="text-sm text-slate-500">Hi {{ $firstName }}. See your booked visits and calls, the tickets you logged, and keep your own notes.</p>
    </div>

    {{-- At a glance --}}
    <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
        <div class="col-span-2 rounded-xl border border-brand-200 bg-gradient-to-br from-brand-50 to-white p-4 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wide text-brand-700">Next visit</p>
            @if ($next)
                <p class="mt-2 text-xl font-extrabold text-brand-800">{{ $next->scheduled_for->format('D, M j') }} &middot; {{ $next->scheduled_for->format('g:i A') }}</p>
                <p class="mt-0.5 truncate text-sm text-slate-600">{{ $next->subject }}</p>
                <div class="mt-2 flex items-center gap-3 text-xs">
                    <span class="rounded-full bg-white px-2 py-0.5 font-semibold text-brand-700 ring-1 ring-inset ring-brand-200">{{ $next->scheduled_for->diffForHumans() }}</span>
                    <a href="{{ route('tickets.show', $next) }}" class="font-semibold text-brand-700 hover:underline">View ticket</a>
                </div>
            @else
                <p class="mt-2 text-xl font-extrabold text-slate-700">Nothing booked</p>
                <p class="mt-0.5 text-sm text-slate-500">Need help? Pick a day and book a visit or call.</p>
                <a href="{{ route('dashboard') }}" class="mt-2 inline-block text-xs font-semibold text-brand-700 hover:underline">Log a ticket</a>
            @endif
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">This week</p>
            <p class="mt-2 text-3xl font-extrabold text-indigo-600">{{ $stats['week'] }}</p>
            <p class="mt-1 text-xs text-slate-400">{{ \Illuminate\Support\Str::plural('booking', $stats['week']) }} in the next 7 days</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Open tickets</p>
            <p class="mt-2 text-3xl font-extrabold text-brand-800">{{ $stats['open'] }}</p>
            <p class="mt-1 text-xs text-slate-400">{{ $stats['notes'] }} {{ \Illuminate\Support\Str::plural('note', $stats['notes']) }} in {{ $month->format('F') }}</p>
        </div>
    </div>

    <div x-data="{ selected: @js($selected->toDateString()) }" class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_23rem]">

        {{-- Calendar --}}
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" aria-label="Your calendar">
            <div class="flex flex-col gap-3 border-b border-slate-100 p-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-2">
                    <a href="{{ route('calendar.index', ['month' => $prev]) }}" aria-label="Previous month"
                       class="rounded-lg p-2 text-slate-500 ring-1 ring-slate-200 transition hover:bg-slate-50 hover:text-slate-800">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m15 5-7 7 7 7"/></svg>
                    </a>
                    <a href="{{ route('calendar.index', ['month' => $nextM]) }}" aria-label="Next month"
                       class="rounded-lg p-2 text-slate-500 ring-1 ring-slate-200 transition hover:bg-slate-50 hover:text-slate-800">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m9 5 7 7-7 7"/></svg>
                    </a>
                    <h3 class="ml-1 text-lg font-bold text-brand-800">{{ $month->format('F Y') }}</h3>
                </div>
                <a href="{{ route('calendar.index') }}" class="self-start rounded-lg px-3.5 py-2 text-sm font-medium text-brand-700 ring-1 ring-brand-200 transition hover:bg-brand-50 sm:self-auto">Today</a>
            </div>

            <div class="grid grid-cols-7 border-b border-slate-100 bg-slate-50 text-center text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                @foreach ($weekdays as $d)
                    <div class="py-2.5">{{ $d }}</div>
                @endforeach
            </div>

            <div class="grid grid-cols-7">
                @foreach ($days as $day)
                    @php
                        $key      = $day->toDateString();
                        $bk       = $bookings->get($key, collect());
                        $lg       = $logged->get($key, collect());
                        $nt       = $notes->get($key, collect());
                        $isToday  = $day->isSameDay($today);
                        $outside  = ! $day->isSameMonth($month);
                        $total    = $bk->count() + $nt->count() + $lg->count();
                        $shown    = 0;
                        $summary  = $bk->count() . ' booking(s), ' . $lg->count() . ' ticket(s) logged, ' . $nt->count() . ' note(s)';
                    @endphp
                    <button type="button" @click="selected = '{{ $key }}'"
                            :class="selected === '{{ $key }}' ? 'bg-brand-50/70 ring-2 ring-inset ring-brand-500' : '{{ $outside ? 'bg-slate-50/70' : 'bg-white' }} hover:bg-slate-50'"
                            :aria-pressed="selected === '{{ $key }}'"
                            aria-label="{{ $day->format('l, F j') }}: {{ $summary }}"
                            class="relative flex min-h-[4.75rem] flex-col items-stretch border-b border-r border-slate-100 p-1.5 text-left transition focus:outline-none focus-visible:z-10 focus-visible:ring-2 focus-visible:ring-brand-500 sm:min-h-[6.5rem]">
                        <span class="flex items-center justify-between">
                            <span class="flex h-6 w-6 items-center justify-center rounded-full text-xs font-semibold sm:h-7 sm:w-7 sm:text-sm
                                {{ $isToday ? 'bg-brand-800 text-white' : ($outside ? 'text-slate-300' : ($day->isWeekend() ? 'text-slate-400' : 'text-slate-700')) }}">{{ $day->day }}</span>
                            @if ($total > 0)
                                <span class="hidden rounded-full bg-slate-100 px-1.5 text-[10px] font-semibold text-slate-500 sm:inline">{{ $total }}</span>
                            @endif
                        </span>

                        {{-- Desktop: chips (bookings first, then notes, then logged tickets) --}}
                        <span class="mt-1.5 hidden flex-col gap-1 sm:flex">
                            @foreach ($bk as $t)
                                @if ($shown < 3)
                                    @php $shown++; @endphp
                                    <span class="block truncate rounded-md border-l-[3px] px-1.5 py-0.5 text-[11px] font-medium ring-1 ring-inset {{ $chip[$t->status] ?? $chip['closed'] }}">
                                        <span class="font-semibold">{{ $t->scheduled_for->format('g:i A') }}</span> {{ $t->subject }}
                                    </span>
                                @endif
                            @endforeach
                            @foreach ($nt as $n)
                                @if ($shown < 3)
                                    @php $shown++; @endphp
                                    <span class="block truncate rounded-md px-1.5 py-0.5 text-[11px] font-medium ring-1 ring-inset {{ $tone[$n->color]['chip'] ?? $tone['amber']['chip'] }}">&#9998; {{ $n->body }}</span>
                                @endif
                            @endforeach
                            @foreach ($lg as $t)
                                @if ($shown < 3)
                                    @php $shown++; @endphp
                                    <span class="block truncate rounded-md bg-white px-1.5 py-0.5 text-[11px] font-medium text-slate-500 ring-1 ring-inset ring-slate-200">&#9679; Ticket logged</span>
                                @endif
                            @endforeach
                            @if ($total > 3)
                                <span class="px-1 text-[11px] font-semibold text-brand-700">+{{ $total - 3 }} more</span>
                            @endif
                        </span>

                        {{-- Mobile: dots --}}
                        @if ($total > 0)
                            <span class="mt-auto flex flex-wrap gap-0.5 pt-1 sm:hidden">
                                @foreach ($bk->take(3) as $t)
                                    <span class="h-1.5 w-1.5 rounded-full {{ $dot[$t->status] ?? 'bg-slate-400' }}"></span>
                                @endforeach
                                @foreach ($nt->take(2) as $n)
                                    <span class="h-1.5 w-1.5 rounded-sm {{ $tone[$n->color]['dot'] ?? 'bg-amber-400' }}"></span>
                                @endforeach
                                @if ($lg->isNotEmpty())
                                    <span class="h-1.5 w-1.5 rounded-full bg-slate-300"></span>
                                @endif
                            </span>
                        @endif
                    </button>
                @endforeach
            </div>

            <div class="flex flex-wrap items-center gap-x-4 gap-y-1.5 border-t border-slate-100 bg-slate-50/60 px-4 py-3 text-xs text-slate-500">
                <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-blue-500"></span>Booked visit or call</span>
                <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-sm bg-amber-400"></span>My note</span>
                <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-slate-300"></span>Ticket logged</span>
                <span class="ml-auto hidden text-slate-400 sm:inline">Click a day to see details and add a note</span>
            </div>
        </section>

        {{-- Day details --}}
        <aside class="rounded-2xl border border-slate-200 bg-white shadow-sm xl:sticky xl:top-24" aria-live="polite">
            @foreach ($days as $day)
                @php
                    $key    = $day->toDateString();
                    $bk     = $bookings->get($key, collect());
                    $lg     = $logged->get($key, collect());
                    $nt     = $notes->get($key, collect());
                    $isPast = $day->lt($today);
                    $label  = $day->isSameDay($today) ? 'Today' : ($day->isSameDay($today->copy()->addDay()) ? 'Tomorrow' : $day->format('l'));
                    $bits   = [];
                    if ($bk->count()) { $bits[] = $bk->count() . ' ' . \Illuminate\Support\Str::plural('booking', $bk->count()); }
                    if ($lg->count()) { $bits[] = $lg->count() . ' ' . \Illuminate\Support\Str::plural('ticket', $lg->count()) . ' logged'; }
                    if ($nt->count()) { $bits[] = $nt->count() . ' ' . \Illuminate\Support\Str::plural('note', $nt->count()); }
                    $mine   = old('note_date') === $key;
                @endphp
                <div x-show="selected === '{{ $key }}'" @if ($key !== $selected->toDateString()) x-cloak @endif>
                    <div class="flex items-start justify-between gap-3 border-b border-slate-100 px-5 py-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $label }}</p>
                            <h3 class="text-lg font-bold text-brand-800">{{ $day->format('F j, Y') }}</h3>
                            <p class="text-sm text-slate-500">{{ $bits ? implode(' · ', $bits) : 'Nothing here yet' }}</p>
                        </div>
                        @unless ($isPast)
                            <a href="{{ route('dashboard', ['book' => $key]) }}" class="shrink-0 rounded-lg bg-brand-800 px-3 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-brand-700">Book a visit</a>
                        @endunless
                    </div>

                    {{-- Bookings --}}
                    @if ($bk->isNotEmpty())
                        <div class="border-b border-slate-100 px-5 py-4">
                            <h4 class="mb-2 text-xs font-bold uppercase tracking-wide text-slate-500">Booked visits and calls</h4>
                            <ol class="space-y-2">
                                @foreach ($bk as $t)
                                    <li class="group relative rounded-xl border border-slate-200 p-3 transition hover:border-brand-300 hover:bg-brand-50/50">
                                        <div class="flex items-start gap-3">
                                            <div class="w-12 shrink-0 text-right">
                                                <p class="text-sm font-bold text-slate-800">{{ $t->scheduled_for->format('g:i') }}</p>
                                                <p class="text-[11px] font-semibold uppercase text-slate-400">{{ $t->scheduled_for->format('A') }}</p>
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <div class="mb-1 flex flex-wrap gap-1.5"><x-ticket-pill :ticket="$t" /></div>
                                                <a href="{{ route('tickets.show', $t) }}" class="block font-semibold leading-snug text-brand-800 after:absolute after:inset-0 after:content-[''] group-hover:underline">{{ $t->subject }}</a>
                                                <p class="text-xs text-slate-500">{{ $t->ticket_no }}{{ $t->supportTypeLabel() ? ' · ' . $t->supportTypeLabel() : '' }}{{ $t->assignee ? ' · ' . $t->assignee->name : '' }}</p>
                                                @if ($t->hasPendingReschedule())
                                                    <p class="mt-1.5 text-xs font-medium text-amber-700">Waiting for approval to move to {{ $t->reschedule_requested_for->format('M j, g:i A') }}</p>
                                                @endif
                                            </div>
                                        </div>
                                    </li>
                                @endforeach
                            </ol>
                        </div>
                    @endif

                    {{-- Tickets logged this day --}}
                    @if ($lg->isNotEmpty())
                        <div class="border-b border-slate-100 px-5 py-4">
                            <h4 class="mb-2 text-xs font-bold uppercase tracking-wide text-slate-500">Tickets you logged</h4>
                            <ul class="space-y-1.5">
                                @foreach ($lg as $t)
                                    <li>
                                        <a href="{{ route('tickets.show', $t) }}" class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm transition hover:bg-slate-50">
                                            <span class="h-2 w-2 shrink-0 rounded-full {{ $dot[$t->status] ?? 'bg-slate-300' }}"></span>
                                            <span class="min-w-0 flex-1 truncate font-medium text-slate-700">{{ $t->subject }}</span>
                                            <span class="shrink-0 text-xs text-slate-400">{{ $t->created_at->format('g:i A') }}</span>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    {{-- Notes --}}
                    <div class="px-5 py-4">
                        <h4 class="mb-2 text-xs font-bold uppercase tracking-wide text-slate-500">My notes <span class="font-medium normal-case text-slate-400">(only you can see these)</span></h4>

                        @if ($nt->isNotEmpty())
                            <ul class="mb-4 space-y-2">
                                @foreach ($nt as $n)
                                    <li x-data="{ edit: false }" class="rounded-xl p-3 ring-1 ring-inset {{ $tone[$n->color]['card'] ?? $tone['amber']['card'] }}">
                                        <div x-show="! edit" class="flex items-start gap-2">
                                            <p class="min-w-0 flex-1 whitespace-pre-line break-words text-sm text-slate-800">{{ $n->body }}</p>
                                            <div class="flex shrink-0 gap-1">
                                                <button type="button" @click="edit = true" class="rounded-md p-1 text-slate-400 transition hover:bg-white/70 hover:text-slate-700" aria-label="Edit note">
                                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 20h4L19 9a2.1 2.1 0 0 0-4-4L4 16z"/></svg>
                                                </button>
                                                <form method="POST" action="{{ route('calendar.notes.destroy', $n) }}" onsubmit="return confirm('Delete this note?')">
                                                    @csrf @method('DELETE')
                                                    <button class="rounded-md p-1 text-slate-400 transition hover:bg-white/70 hover:text-red-600" aria-label="Delete note">
                                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 7h14M10 11v6M14 11v6M6 7l1 12h10l1-12M9 7V4h6v3"/></svg>
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                        <form x-show="edit" x-cloak method="POST" action="{{ route('calendar.notes.update', $n) }}" class="space-y-2">
                                            @csrf @method('PATCH')
                                            <textarea name="body" rows="3" maxlength="500" required class="{{ $field }}">{{ $n->body }}</textarea>
                                            <div class="flex items-center justify-between gap-2">
                                                <div class="flex gap-1.5" role="radiogroup" aria-label="Note colour">
                                                    @foreach ($tone as $name => $t2)
                                                        <label class="cursor-pointer">
                                                            <input type="radio" name="color" value="{{ $name }}" @checked($n->color === $name) class="peer sr-only">
                                                            <span class="block h-5 w-5 rounded-full {{ $t2['pick'] }} ring-offset-1 peer-checked:ring-2 peer-checked:ring-slate-700 peer-focus-visible:ring-2 peer-focus-visible:ring-brand-500" title="{{ ucfirst($name) }}"></span>
                                                        </label>
                                                    @endforeach
                                                </div>
                                                <div class="flex gap-2">
                                                    <button type="button" @click="edit = false" class="rounded-lg px-3 py-1.5 text-xs font-medium text-slate-600 hover:bg-white/70">Cancel</button>
                                                    <button class="rounded-lg bg-brand-800 px-3 py-1.5 text-xs font-semibold text-white hover:bg-brand-700">Save</button>
                                                </div>
                                            </div>
                                        </form>
                                    </li>
                                @endforeach
                            </ul>
                        @endif

                        <form method="POST" action="{{ route('calendar.notes.store') }}" class="space-y-2">
                            @csrf
                            <input type="hidden" name="note_date" value="{{ $key }}">
                            <label for="note-{{ $key }}" class="sr-only">Add a note for {{ $day->format('F j') }}</label>
                            <textarea id="note-{{ $key }}" name="body" rows="2" maxlength="500" required class="{{ $field }}"
                                      placeholder="{{ $nt->isEmpty() ? 'Add a note: a reminder, who will be on site, what to prepare...' : 'Add another note...' }}">{{ $mine ? old('body') : '' }}</textarea>
                            @if ($mine)
                                @error('body') <p role="alert" class="text-sm text-red-600">{{ $message }}</p> @enderror
                            @endif
                            <div class="flex items-center justify-between gap-2">
                                <div class="flex gap-1.5" role="radiogroup" aria-label="Note colour">
                                    @foreach ($tone as $name => $t2)
                                        <label class="cursor-pointer">
                                            <input type="radio" name="color" value="{{ $name }}" @checked(($mine ? old('color', 'amber') : 'amber') === $name) class="peer sr-only">
                                            <span class="block h-5 w-5 rounded-full {{ $t2['pick'] }} ring-offset-1 peer-checked:ring-2 peer-checked:ring-slate-700 peer-focus-visible:ring-2 peer-focus-visible:ring-brand-500" title="{{ ucfirst($name) }}"></span>
                                        </label>
                                    @endforeach
                                </div>
                                <button class="rounded-lg bg-brand-800 px-4 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-brand-700">Add note</button>
                            </div>
                        </form>
                    </div>
                </div>
            @endforeach
        </aside>
    </div>
</x-app-layout>
