<x-app-layout>
    <x-slot name="header">{{ $ticket->ticket_no }}</x-slot>

    @php
        $me         = auth()->user();
        $isOwner    = $me->id === $ticket->user_id;
        $isAdmin    = in_array($me->role, ['admin', 'super_admin']);
        $isEngineer = $me->role === 'it_support';
        $isStaff    = $me->isStaff();
        $jmsLocked  = $isAdmin && $ticket->isLockedToJms($me); // JMS dispatched it: a partner admin can no longer reassign it
        $final      = $ticket->isFinished();
        $isJms      = $me->canDispatchJms();                // super admin or JMS admin: the only people who dispatch JMS engineers
        $askingJms  = $ticket->isAskingForJms();            // the company asked JMS to take over, and nobody has accepted yet
        $canAskJms  = ! $isJms && $me->company_id && ! $final && ! $jmsLocked && ! $askingJms
                      && ($me->role === 'admin' || ($isEngineer && (int) $ticket->assigned_to === (int) $me->id));
        $cancelled  = $ticket->status === 'cancelled';
        $steps = ['open' => 'Submitted', 'assigned' => 'Assigned', 'in_progress' => 'In progress', 'resolved' => 'Resolved', 'closed' => 'Closed'];
        $idx = match ($ticket->status) { 'open' => 0, 'assigned' => 1, 'in_progress', 'on_hold' => 2, 'resolved' => 3, 'closed' => 4, default => -1 };
        $sla = $isStaff ? $ticket->slaBadge() : null;
        $field = 'w-full rounded-lg border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500';
        $card  = 'rounded-2xl border border-slate-200 bg-white shadow-sm';
    @endphp

    {{-- Live update: someone else changed this ticket while it is open. Reload is manual so a half-written reply is never lost. --}}
    <div x-data="{ stale: false }" x-show="stale" x-cloak role="status" aria-live="polite"
         @live-ticket.window="if ($event.detail.ticket_id === {{ $ticket->id }} && $event.detail.actor_id !== {{ $me->id }}) stale = true"
         class="mb-4 flex flex-col gap-2 rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900 sm:flex-row sm:items-center sm:justify-between">
        <span>This ticket was just updated by someone else.</span>
        <a href="{{ request()->fullUrl() }}" class="shrink-0 font-semibold text-sky-700 hover:underline">Reload to see it</a>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">

            {{-- Ticket + progress --}}
            <div class="{{ $card }} p-5 sm:p-6">
                <div class="mb-3 flex flex-wrap items-center gap-2">
                    <span class="rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset {{ $ticket->statusClasses() }}">{{ $ticket->statusLabel() }}</span>
                    <span class="rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset {{ $ticket->priorityClasses() }}">{{ $ticket->priorityLabel() }} priority</span>
                    <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs text-slate-600">{{ $ticket->category }}</span>
                    @if ($ticket->supportTypeLabel()) <span class="rounded-full bg-brand-50 px-2.5 py-1 text-xs font-medium text-brand-700">{{ $ticket->supportTypeLabel() }}</span> @endif
                    @if ($ticket->scheduledShort()) <span class="rounded-full bg-indigo-50 px-2.5 py-1 text-xs font-medium text-indigo-700">Scheduled {{ $ticket->scheduledShort() }}</span> @endif
                    @if ($sla && ($clock = $ticket->slaClock()))
                        {{-- Live SLA countdown: ticks every second; the server text shows until the script starts. --}}
                        <span class="ml-auto text-right" x-data="slaCountdown({{ $clock['due'] }}, {{ $clock['start'] }})" x-init="start()">
                            <span class="block text-xs tabular-nums" :class="cls" x-text="label">{{ $sla[0] }}</span>
                            <span class="mt-1 block h-1 w-28 overflow-hidden rounded-full bg-slate-200" aria-hidden="true">
                                <span class="block h-full rounded-full transition-all" :class="bar" :style="`width:${pct}%`"></span>
                            </span>
                        </span>
                    @elseif ($sla)
                        <span class="ml-auto text-xs {{ $sla[1] }}">{{ $sla[0] }}</span>
                    @endif
                </div>
                <div class="flex items-start justify-between gap-3">
                    <h2 class="text-xl font-bold text-brand-800">{{ $ticket->subject }}</h2>
                    @if ($isAdmin && ! in_array($ticket->status, ['cancelled', 'closed']))
                        <a href="{{ route('tickets.edit', $ticket) }}" class="inline-flex shrink-0 items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-medium text-slate-700 ring-1 ring-slate-300 hover:bg-slate-50">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 20h4L19 9a2.8 2.8 0 0 0-4-4L4 16v4zM13.5 6.5l4 4"/></svg> Edit
                        </a>
                    @endif
                </div>
                <p class="mt-1 text-xs text-slate-500">Submitted by {{ $ticket->user->name }} <x-admin-badge :user="$ticket->user" class="align-middle" /> &middot; {{ $ticket->created_at->format('M d, Y h:i A') }}</p>
                @if ($askingJms) <x-jms-requested-badge :ticket="$ticket" class="mt-2" /> @endif

                @if ($cancelled)
                    <div class="mt-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">This ticket was cancelled and is no longer being worked on.</div>
                @else
                    <ol class="mt-6 flex items-start">
                        @foreach ($steps as $key => $label)
                            @php($i = $loop->index)
                            <li class="relative flex flex-1 flex-col items-center text-center {{ $loop->last ? '' : 'after:absolute after:left-1/2 after:top-3.5 after:h-0.5 after:w-full ' . ($i < $idx ? 'after:bg-brand-600' : 'after:bg-slate-200') }}">
                                <span class="relative z-10 flex h-7 w-7 items-center justify-center rounded-full text-xs font-bold
                                    {{ $i < $idx ? 'bg-brand-600 text-white' : ($i === $idx ? 'bg-brand-800 text-white ring-4 ring-brand-100' : 'bg-slate-200 text-slate-500') }}">
                                    @if ($i < $idx) <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7"/></svg> @else {{ $i + 1 }} @endif
                                </span>
                                <span class="mt-2 text-xs font-medium {{ $i <= $idx ? 'text-slate-800' : 'text-slate-400' }}">{{ $label }}</span>
                                @if ($ticket->status === 'on_hold' && $key === 'in_progress') <span class="mt-0.5 rounded bg-amber-50 px-1.5 text-[10px] font-semibold text-amber-700">ON HOLD</span> @endif
                            </li>
                        @endforeach
                    </ol>
                @endif

                <div class="mt-6 border-t border-slate-100 pt-5">
                    <h3 class="mb-2 text-sm font-semibold text-slate-700">Description</h3>
                    <p class="whitespace-pre-line text-sm leading-relaxed text-slate-700">{{ $ticket->description }}</p>
                </div>

                @php($submitted = $ticket->attachments->whereNull('ticket_comment_id'))
                @if ($submitted->isNotEmpty())
                    <div class="mt-5 border-t border-slate-100 pt-5">
                        <h3 class="mb-2 text-sm font-semibold text-slate-700">Attachments <span class="font-normal text-slate-400">({{ $submitted->count() }})</span></h3>
                        <x-attachment-list :ticket="$ticket" :attachments="$submitted" />
                    </div>
                @endif
            </div>

            {{-- Resolution --}}
            @if ($ticket->resolution)
                <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5 sm:p-6">
                    <h3 class="text-sm font-semibold text-emerald-900">Resolution</h3>
                    <p class="mt-2 whitespace-pre-line text-sm leading-relaxed text-emerald-900">{{ $ticket->resolution }}</p>
                    @if ($ticket->rating)
                        <p class="mt-3 border-t border-emerald-200 pt-3 text-xs text-emerald-800">
                            Requester rating: <span class="font-semibold">{{ $ticket->rating }}/5</span>{{ $ticket->rating_comment ? ' - ' . $ticket->rating_comment : '' }}
                        </p>
                    @endif
                </div>
            @endif

            {{-- Conversation --}}
            <div class="{{ $card }} p-5 sm:p-6">
                <h3 class="mb-4 font-semibold text-brand-800">Conversation</h3>
                <div class="space-y-4">
                    @forelse ($comments as $c)
                        <div class="flex gap-3">
                            <x-avatar :user="$c->user" size="h-9 w-9" class="mt-1" />
                            <div class="min-w-0 flex-1 rounded-xl p-4 {{ $c->is_internal ? 'border border-amber-200 bg-amber-50' : ($c->user->isStaff() ? 'bg-brand-50' : 'bg-slate-50') }}">
                                <div class="mb-1 flex flex-wrap items-center gap-2 text-xs text-slate-500">
                                    <span class="font-semibold text-slate-800">{{ $c->user->name }}</span>
                                    <span>{{ $c->user->roleLabel() }}</span>
                                    @if ($c->is_internal) <span class="rounded bg-amber-200 px-1.5 text-amber-900">Internal note</span> @endif
                                    <span>&middot; {{ $c->created_at->diffForHumans() }}</span>
                                </div>
                                <p class="whitespace-pre-line text-sm">{{ $c->body }}</p>
                                @if ($c->attachments->isNotEmpty())
                                    <x-attachment-list :ticket="$ticket" :attachments="$c->attachments" class="mt-3" />
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">No replies yet. Our team will respond here.</p>
                    @endforelse
                </div>

                @unless ($cancelled)
                    <form method="POST" action="{{ route('tickets.comment', $ticket) }}" enctype="multipart/form-data" class="mt-5 space-y-3">
                        @csrf
                        @if ($isStaff)
                            <div x-data="cannedPicker(@js($cannedReplies))" class="flex flex-wrap items-center gap-2">
                                <select x-model="picked" @change="insert()" class="rounded-lg border-slate-300 py-1.5 text-sm text-slate-600 focus:border-brand-500 focus:ring-brand-500">
                                    <option value="">Insert a saved reply...</option>
                                    <template x-for="r in replies" :key="r.id"><option :value="r.id" x-text="r.title"></option></template>
                                </select>
                                <a href="{{ route('canned-replies.index') }}" class="text-xs font-semibold text-brand-600 hover:underline">Manage saved replies</a>
                            </div>
                        @endif
                        <textarea name="body" id="reply-body" rows="3" placeholder="Write a reply..." class="{{ $field }}"></textarea>
                        @error('body') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                        <x-attachment-picker label="Attach a screenshot or log file">Add a screenshot, photo of the error or log file to your reply.</x-attachment-picker>
                        <div class="flex items-center justify-between">
                            @if ($isStaff)
                                <label class="flex items-center gap-2 text-sm text-slate-600">
                                    <input type="checkbox" name="is_internal" value="1" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500"> Internal note (hidden from the requester)
                                </label>
                            @else <span></span> @endif
                            <button class="rounded-lg bg-brand-800 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">Send reply</button>
                        </div>
                    </form>
                @endunless
            </div>

            {{-- Timeline --}}
            @if ($timeline->isNotEmpty())
                <div class="{{ $card }} p-5 sm:p-6">
                    <h3 class="mb-4 font-semibold text-brand-800">Activity timeline</h3>
                    <ol class="relative ml-3 space-y-5 border-l border-slate-200 pl-6">
                        @foreach ($timeline as $a)
                            @php([$cls, $paths] = $a->look())
                            <li class="relative">
                                <span class="absolute -left-[37px] flex h-6 w-6 items-center justify-center rounded-full ring-4 ring-white {{ $cls }}">
                                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $paths !!}</svg>
                                </span>
                                <p class="text-sm text-slate-700">{{ $a->description }}</p>
                                <p class="text-xs text-slate-400">{{ $a->user?->name }} &middot; {{ $a->whenLabel() }}</p>
                            </li>
                        @endforeach
                    </ol>
                </div>
            @endif
        </div>

        {{-- Right column --}}
        <div class="space-y-6">

            {{-- Partner admin: JMS is on it --}}
            @if ($jmsLocked && ! $final)
                <div class="rounded-2xl border border-slate-200 bg-indigo-50 p-5 text-sm text-indigo-800">
                    <p class="font-semibold text-indigo-700">Handled by JMS support</p>
                    <p class="mt-1 text-indigo-800">{{ $ticket->assignee->name }} from JMS is working on this ticket. Use the reply box to add details or ask for an update.</p>
                </div>
            @endif

            {{-- JMS support was requested --}}
            @if ($askingJms && $isStaff)
                <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900">
                    <p class="font-semibold text-amber-800">{{ $isJms ? 'JMS support was requested' : 'Waiting for JMS support' }}</p>
                    <p class="mt-1">
                        @if ($isJms) The company could not solve this ticket and asked JMS to take over. Accept it below and choose a JMS engineer.
                        @else JMS has been notified and will assign one of its engineers. You will be told as soon as they do. @endif
                    </p>
                </div>
            @endif

            {{-- Admin: accept & assign --}}
            @if ($isAdmin && ! $final && ! $jmsLocked && ($isJms || $engineers->isNotEmpty()))
                <form id="assign" method="POST" action="{{ route('tickets.assign', $ticket) }}"
                      class="scroll-mt-24 space-y-4 rounded-2xl border bg-white p-5 shadow-sm {{ $ticket->assigned_to ? 'border-slate-200' : 'border-violet-300 ring-4 ring-violet-100' }}">
                    @csrf
                    <div>
                        <h3 class="font-semibold text-brand-800">{{ $ticket->assigned_to ? 'Reassign / dispatch' : 'Accept & assign' }}</h3>
                        <p class="text-xs text-slate-500">{{ $ticket->assigned_to ? 'Change the engineer or how the visit is done.' : 'Accept this ticket and hand it to an IT engineer.' }}</p>
                    </div>

                    <div>
                        <span class="mb-2 block text-sm font-medium text-slate-700">IT engineer</span>
                        @include('tickets._engineer-cards', ['engineers' => $engineers, 'workload' => $workload, 'ticket' => $ticket])
                        @if ($engineers->isEmpty())
                            <p class="mt-1 text-xs text-amber-700">
                                @if ($isJms) No JMS support engineers yet. Create one under Users (choose "JMS One IT (our team)"). @else Your company has no IT engineer yet. @endif
                            </p>
                        @else
                            <p class="mt-1 text-xs text-slate-500">
                                @if ($isJms) Only JMS support engineers can be assigned from here. A partner company's own IT team is assigned by that company's admin.
                                @else Choose one of your company's IT engineers. JMS engineers are assigned by JMS: use the Need JMS support box below if your team cannot solve it. @endif
                            </p>
                        @endif
                        @error('assigned_to') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <span class="mb-1 block text-sm font-medium text-slate-700">Support type</span>
                        <div class="grid grid-cols-2 gap-2">
                            @foreach (\App\Models\Ticket::SUPPORT_TYPES as $k => $label)
                                <label class="cursor-pointer">
                                    <input type="radio" name="support_type" value="{{ $k }}" class="peer sr-only" @checked(old('support_type', $ticket->support_type) === $k)>
                                    <span class="flex items-center justify-center rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-600 transition peer-checked:border-brand-600 peer-checked:bg-brand-50 peer-checked:text-brand-700">{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                        @error('support_type') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Priority</label>
                        <select name="priority" class="{{ $field }}">
                            @foreach (\App\Models\Ticket::PRIORITIES as $p)
                                <option value="{{ $p }}" @selected(old('priority', $ticket->priority) === $p)>{{ ucfirst($p) }} (target {{ \App\Models\Ticket::SLA_HOURS[$p] }}h)</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Note for the engineer <span class="font-normal text-slate-400">(optional)</span></label>
                        <textarea name="note" rows="2" maxlength="500" class="{{ $field }}" placeholder="e.g. Bring a spare router. Ask for Ms. Reyes at reception.">{{ old('note') }}</textarea>
                    </div>

                    <button class="w-full rounded-lg bg-violet-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-violet-700">{{ $ticket->assigned_to ? 'Save assignment' : 'Accept & assign' }}</button>
                </form>
            @elseif ($isAdmin && ! $final && ! $jmsLocked)
                {{-- A partner admin whose company has no IT engineer: JMS does the dispatching. --}}
                <div class="rounded-2xl border border-slate-200 bg-white p-5 text-sm shadow-sm">
                    <p class="font-semibold text-brand-800">JMS will assign an engineer</p>
                    <p class="mt-1 text-slate-600">Your company has no IT engineer of its own, so JMS support accepts this ticket and assigns one of its engineers. You can follow progress on this page and in your notifications.</p>
                </div>
            @endif

            {{-- Partner admin or the company's own engineer: hand a ticket they cannot solve to JMS --}}
            @if ($canAskJms)
                <form method="POST" action="{{ route('tickets.request-jms', $ticket) }}" class="space-y-3 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    @csrf
                    <div>
                        <h3 class="font-semibold text-brand-800">Need JMS support?</h3>
                        <p class="text-xs text-slate-500">If your team cannot solve this, ask JMS to take over. JMS reviews the request and assigns one of its engineers.</p>
                    </div>
                    <textarea name="jms_note" rows="3" maxlength="1000" required class="{{ $field }}" placeholder="What did you already try? What does JMS need to know?">{{ old('jms_note') }}</textarea>
                    @error('jms_note') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                    <button onclick="return confirm('Ask JMS support to take over this ticket?')" class="w-full rounded-lg bg-amber-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-amber-700">Request JMS support</button>
                </form>
            @endif

            {{-- Engineer: update progress --}}
            @if ($isEngineer && ! $final)
                <div class="{{ $card }} space-y-3 p-5">
                    <h3 class="font-semibold text-brand-800">Update progress</h3>

                    @if (in_array($ticket->status, ['assigned', 'on_hold']))
                        <form method="POST" action="{{ route('tickets.progress', $ticket) }}">
                            @csrf <input type="hidden" name="status" value="in_progress">
                            <button class="w-full rounded-lg bg-brand-800 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-700">{{ $ticket->status === 'on_hold' ? 'Resume work' : 'Start work' }}</button>
                        </form>
                    @endif

                    @if ($ticket->status === 'in_progress')
                        <form method="POST" action="{{ route('tickets.progress', $ticket) }}">
                            @csrf <input type="hidden" name="status" value="on_hold">
                            <button class="w-full rounded-lg px-4 py-2.5 text-sm font-medium text-slate-700 ring-1 ring-slate-300 hover:bg-slate-50">Put on hold</button>
                        </form>
                        <form method="POST" action="{{ route('tickets.progress', $ticket) }}" class="space-y-2 border-t border-slate-100 pt-3">
                            @csrf <input type="hidden" name="status" value="resolved">
                            <label class="block text-sm font-medium text-slate-700">What did you do to fix it?</label>
                            <textarea name="resolution" rows="3" maxlength="3000" class="{{ $field }}" placeholder="Describe the fix. The requester will see this.">{{ old('resolution') }}</textarea>
                            @error('resolution') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                            <button class="w-full rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700">Mark as resolved</button>
                        </form>
                    @endif
                </div>
            @endif

            {{-- Requester: confirm or reopen --}}
            @if ($isOwner && $ticket->status === 'resolved')
                <div class="space-y-4 rounded-2xl border border-emerald-200 bg-white p-5 shadow-sm">
                    <div>
                        <h3 class="font-semibold text-emerald-800">Is your problem fixed?</h3>
                        <p class="text-xs text-slate-500">Confirm and rate the support to close this ticket.</p>
                    </div>
                    <form method="POST" action="{{ route('tickets.feedback', $ticket) }}" class="space-y-3">
                        @csrf
                        <div class="flex gap-2">
                            @foreach ([1, 2, 3, 4, 5] as $n)
                                <label class="flex-1 cursor-pointer">
                                    <input type="radio" name="rating" value="{{ $n }}" class="peer sr-only" @checked((int) old('rating') === $n)>
                                    <span class="flex items-center justify-center rounded-lg border border-slate-300 py-2 text-sm font-semibold text-slate-500 transition peer-checked:border-emerald-600 peer-checked:bg-emerald-50 peer-checked:text-emerald-700">{{ $n }}</span>
                                </label>
                            @endforeach
                        </div>
                        <p class="flex justify-between text-[11px] text-slate-400"><span>Poor</span><span>Excellent</span></p>
                        @error('rating') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                        <textarea name="comment" rows="2" maxlength="500" class="{{ $field }}" placeholder="Any comments? (optional)"></textarea>
                        <button class="w-full rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700">Yes, it's fixed - close ticket</button>
                    </form>
                    <details class="border-t border-slate-100 pt-3" @if ($errors->has('reason')) open @endif>
                        <summary class="cursor-pointer text-sm font-medium text-rose-700">Not fixed? Reopen the ticket</summary>
                        <form method="POST" action="{{ route('tickets.reopen', $ticket) }}" class="mt-3 space-y-2">
                            @csrf
                            <textarea name="reason" rows="2" maxlength="500" class="{{ $field }}" placeholder="What is still not working?">{{ old('reason') }}</textarea>
                            @error('reason') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                            <button class="w-full rounded-lg px-4 py-2 text-sm font-semibold text-rose-700 ring-1 ring-rose-200 hover:bg-rose-50">Reopen ticket</button>
                        </form>
                    </details>
                </div>
            @endif

            {{-- Requester: cancel --}}
            @if ($isOwner && $ticket->isCancellable())
                <div class="rounded-2xl border border-rose-200 bg-white p-5 shadow-sm">
                    <h3 class="font-semibold text-slate-800">Cancel request</h3>
                    <p class="mt-1 text-sm text-slate-500">No one has accepted this ticket yet, so you can still cancel it.</p>
                    <button type="button" x-data x-on:click="$dispatch('open-modal', 'cancel-{{ $ticket->id }}')" class="mt-3 w-full rounded-lg px-4 py-2 text-sm font-semibold text-rose-700 ring-1 ring-rose-200 hover:bg-rose-50">Cancel ticket</button>
                </div>
                @include('tickets._cancel-modal', ['ticket' => $ticket])
            @endif

            {{-- Staff: where to go / how to reach the requester --}}
            @if ($isStaff && ! $cancelled)
                <div class="{{ $card }} p-5">
                    <div class="mb-3 flex items-center justify-between">
                        <h3 class="font-semibold text-brand-800">Service visit</h3>
                        @if ($ticket->supportTypeLabel()) <span class="rounded-full bg-brand-50 px-2.5 py-1 text-xs font-medium text-brand-700">{{ $ticket->supportTypeLabel() }}</span>
                        @else <span class="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-700">Not decided</span> @endif
                    </div>

                    @unless ($final)
                        <form method="POST" action="{{ route('tickets.support-type', $ticket) }}" class="mb-3">
                            @csrf
                            <p class="mb-1.5 text-xs font-medium text-slate-500">Decide how to handle this ticket</p>
                            <div class="grid grid-cols-2 gap-2">
                                @foreach (\App\Models\Ticket::SUPPORT_TYPES as $k => $label)
                                    <button name="support_type" value="{{ $k }}" class="rounded-lg px-3 py-2 text-sm font-medium ring-1 transition {{ $ticket->support_type === $k ? 'bg-brand-800 text-white ring-brand-800' : 'text-slate-700 ring-slate-300 hover:bg-slate-50' }}">{{ $label }}</button>
                                @endforeach
                            </div>
                            @error('support_type') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </form>
                    @endunless

                    @if ($ticket->support_type === 'onsite')
                        @if ($ticket->location)
                            <p class="mb-3 break-words text-sm text-slate-700">{{ $ticket->location }}</p>
                            <div class="grid grid-cols-2 gap-2">
                                <a href="{{ $ticket->mapsUrl() }}" target="_blank" rel="noopener" class="rounded-lg px-3 py-2 text-center text-sm font-medium text-brand-700 ring-1 ring-brand-200 hover:bg-brand-50">Open in Maps</a>
                                <a href="{{ $ticket->directionsUrl() }}" target="_blank" rel="noopener" class="rounded-lg bg-brand-800 px-3 py-2 text-center text-sm font-semibold text-white hover:bg-brand-700">Directions</a>
                            </div>
                        @else
                            <p class="text-sm text-slate-500">No address was provided. Contact the requester for directions.</p>
                        @endif
                    @elseif ($ticket->support_type === 'remote')
                        <p class="text-sm text-slate-600">Remote support. Contact the requester to start a session.</p>
                    @else
                        <p class="text-sm text-slate-500">Choose remote or on-site above to get started.</p>
                    @endif

                    <div class="mt-3 grid grid-cols-2 gap-2 border-t border-slate-100 pt-3">
                        @if ($ticket->contact_phone)
                            <a href="tel:{{ preg_replace('/[^0-9+]/', '', $ticket->contact_phone) }}" class="rounded-lg px-3 py-2 text-center text-sm font-medium text-slate-700 ring-1 ring-slate-300 hover:bg-slate-50">Call</a>
                        @endif
                        <a href="mailto:{{ $ticket->user->email }}?subject={{ rawurlencode('Re: ' . $ticket->ticket_no . ' ' . $ticket->subject) }}" class="rounded-lg px-3 py-2 text-center text-sm font-medium text-slate-700 ring-1 ring-slate-300 hover:bg-slate-50 {{ $ticket->contact_phone ? '' : 'col-span-2' }}">Email</a>
                    </div>
                </div>
            @endif

            {{-- Details --}}
            <div class="{{ $card }} p-5">
                <h3 class="mb-4 font-semibold text-brand-800">Ticket details</h3>
                <div class="mb-4 flex items-center gap-3 rounded-xl bg-slate-50 p-3">
                    <x-avatar :user="$ticket->user" size="h-11 w-11" />
                    <div class="min-w-0">
                        <p class="flex items-center gap-1.5 text-sm font-semibold text-slate-800"><span class="truncate">{{ $ticket->user->name }}</span><x-admin-badge :user="$ticket->user" /></p>
                        <p class="truncate text-xs text-slate-500">{{ $ticket->user->company ?: 'Requester' }}</p>
                    </div>
                </div>
                <dl class="space-y-3 text-sm">
                    @foreach ([
                        ['Company', $ticket->company?->name ?? $ticket->user->company ?? '-'],
                        ['When needed', $ticket->whenLabel()],
                        ['Contact number', $ticket->contact_phone ?: '-'],
                        ['Location', $ticket->location ?: '-'],
                        ['Support type', $ticket->supportTypeLabel() ?? ($isStaff ? 'Not decided yet' : 'To be decided by our IT team')],
                        ['Assigned engineer', $ticket->assignee?->name ?? 'Not assigned yet'],
                        ['Accepted', $ticket->accepted_at ? $ticket->accepted_at->format('M d, Y h:i A') . ($ticket->acceptor ? ' by ' . $ticket->acceptor->name : '') : 'Waiting for acceptance'],
                        ['Target resolution', in_array($ticket->status, ['open', 'assigned', 'in_progress', 'on_hold']) ? $ticket->slaDueAt()->format('M d, Y h:i A') : '-'],
                        ['Last updated', $ticket->updated_at->diffForHumans()],
                        ['Resolved', $ticket->resolved_at ? $ticket->resolved_at->format('M d, Y h:i A') : '-'],
                    ] as [$label, $value])
                        <div class="flex justify-between gap-4 border-b border-slate-50 pb-2 last:border-0 last:pb-0">
                            <dt class="shrink-0 text-slate-500">{{ $label }}</dt>
                            <dd class="break-words text-right font-medium text-slate-800">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>

            {{-- Admin: manual override --}}
            @if ($isAdmin && ! $jmsLocked)
                <details class="{{ $card }} p-5">
                    <summary class="cursor-pointer text-sm font-semibold text-slate-700">Advanced: override status</summary>
                    <form method="POST" action="{{ route('tickets.update', $ticket) }}" class="mt-4 space-y-4">
                        @csrf @method('PATCH')
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">Status</label>
                            <select name="status" class="{{ $field }}">
                                @foreach (\App\Models\Ticket::STATUSES as $s)
                                    <option value="{{ $s }}" @selected($ticket->status === $s)>{{ ucwords(str_replace('_', ' ', $s)) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">Priority</label>
                            <select name="priority" class="{{ $field }}">
                                @foreach (\App\Models\Ticket::PRIORITIES as $p)
                                    <option value="{{ $p }}" @selected($ticket->priority === $p)>{{ ucfirst($p) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">Engineer</label>
                            <select name="assigned_to" class="{{ $field }}">
                                <option value="">Unassigned</option>
                                @foreach ($engineers as $e)
                                    <option value="{{ $e->id }}" @selected((int) $ticket->assigned_to === $e->id)>{{ $e->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button class="w-full rounded-lg bg-slate-800 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-700">Save changes</button>
                    </form>
                </details>
            @endif
        </div>
    </div>
    <script>
        document.addEventListener('alpine:init', () => {
            /** Live SLA countdown: due/start are Unix milliseconds. */
            Alpine.data('slaCountdown', (due, start) => ({
                label: '', cls: 'text-slate-500', bar: 'bg-emerald-500', pct: 0, timer: null,
                start() { this.tick(); this.timer = setInterval(() => this.tick(), 1000); },
                fmt(ms) {
                    const t = Math.floor(Math.abs(ms) / 1000);
                    const d = Math.floor(t / 86400), h = Math.floor(t % 86400 / 3600), m = Math.floor(t % 3600 / 60), s = t % 60;
                    const p = (n) => String(n).padStart(2, '0');
                    return d > 0 ? `${d}d ${h}h ${p(m)}m` : (h > 0 ? `${h}h ${p(m)}m ${p(s)}s` : `${m}m ${p(s)}s`);
                },
                tick() {
                    const left = due - Date.now();
                    const total = Math.max(1, due - start);
                    this.pct = Math.min(100, Math.max(0, Math.round((total - left) / total * 100)));
                    if (left < 0) {
                        this.label = 'Overdue by ' + this.fmt(left);
                        this.cls = 'text-red-600 font-semibold'; this.bar = 'bg-red-500'; this.pct = 100;
                    } else {
                        this.label = 'Due in ' + this.fmt(left);
                        const hot = left < 2 * 3600 * 1000;
                        this.cls = hot ? 'text-orange-600 font-medium' : 'text-slate-500';
                        this.bar = hot ? 'bg-orange-500' : 'bg-emerald-500';
                    }
                },
            }));

            /** Drops a saved reply into the reply box (adds to what is already typed, never overwrites it). */
            Alpine.data('cannedPicker', (replies) => ({
                replies, picked: '',
                insert() {
                    const r = this.replies.find((x) => String(x.id) === String(this.picked));
                    const box = document.getElementById('reply-body');
                    if (r && box) {
                        box.value = box.value.trim() === '' ? r.text : box.value.replace(/\s+$/, '') + '\n\n' + r.text;
                        box.focus();
                        box.setSelectionRange(box.value.length, box.value.length);
                    }
                    this.picked = '';
                },
            }));
        });
    </script>
</x-app-layout>
