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
        $cancelled  = $ticket->status === 'cancelled';
        $steps = ['open' => 'Submitted', 'assigned' => 'Assigned', 'in_progress' => 'In progress', 'resolved' => 'Resolved', 'closed' => 'Closed'];
        $idx = match ($ticket->status) { 'open' => 0, 'assigned' => 1, 'in_progress', 'on_hold' => 2, 'resolved' => 3, 'closed' => 4, default => -1 };
        $sla = $isStaff ? $ticket->slaBadge() : null;
        $field = 'w-full rounded-lg border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500';
        $card  = 'rounded-2xl border border-slate-200 bg-white shadow-sm';
    @endphp

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
                    @if ($sla) <span class="ml-auto text-xs {{ $sla[1] }}">{{ $sla[0] }}</span> @endif
                </div>
                <div class="flex items-start justify-between gap-3">
                    <h2 class="text-xl font-bold text-brand-800">{{ $ticket->subject }}</h2>
                    @if ($isAdmin && ! in_array($ticket->status, ['cancelled', 'closed']))
                        <a href="{{ route('tickets.edit', $ticket) }}" class="inline-flex shrink-0 items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-medium text-slate-700 ring-1 ring-slate-300 hover:bg-slate-50">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 20h4L19 9a2.8 2.8 0 0 0-4-4L4 16v4zM13.5 6.5l4 4"/></svg> Edit
                        </a>
                    @endif
                </div>
                <p class="mt-1 text-xs text-slate-500">Submitted by {{ $ticket->user->name }} &middot; {{ $ticket->created_at->format('M d, Y h:i A') }}</p>

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
                        <textarea name="body" rows="3" placeholder="Write a reply..." class="{{ $field }}"></textarea>
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

            {{-- Admin: accept & assign --}}
            @if ($isAdmin && ! $final && ! $jmsLocked)
                <form id="assign" method="POST" action="{{ route('tickets.assign', $ticket) }}"
                      class="scroll-mt-24 space-y-4 rounded-2xl border bg-white p-5 shadow-sm {{ $ticket->assigned_to ? 'border-slate-200' : 'border-violet-300 ring-4 ring-violet-100' }}">
                    @csrf
                    <div>
                        <h3 class="font-semibold text-brand-800">{{ $ticket->assigned_to ? 'Reassign / dispatch' : 'Accept & assign' }}</h3>
                        <p class="text-xs text-slate-500">{{ $ticket->assigned_to ? 'Change the engineer or how the visit is done.' : 'Accept this ticket and hand it to an IT engineer.' }}</p>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">IT engineer</label>
                        <select name="assigned_to" class="{{ $field }}" required>
                            @unless ($ticket->assigned_to) <option value="">Choose an engineer...</option> @endunless
                            @foreach ($engineers->groupBy(fn ($e) => $e->isJmsEngineer() ? 'JMS support team' : 'Company IT team') as $group => $list)
                                <optgroup label="{{ $group }}">
                                    @foreach ($list as $e)
                                        <option value="{{ $e->id }}" @selected((int) old('assigned_to', $ticket->assigned_to) === $e->id)>{{ $e->name }} &middot; {{ $workload[$e->id] ?? 0 }} active</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                        @if ($engineers->isEmpty())
                            <p class="mt-1 text-xs text-amber-700">
                                @if ($me->canDispatchJms()) No IT Support accounts yet. Create one under Users (choose "JMS One IT (our team)" for our own engineers).
                                @else No IT engineers are available yet. Ask JMS to add one. @endif
                            </p>
                        @endif
                        @if ($engineers->isNotEmpty())
                            <p class="mt-1 text-xs text-slate-500">Choose one of the JMS support engineers, or your own company's IT team if you have one.</p>
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
                        <p class="truncate text-sm font-semibold text-slate-800">{{ $ticket->user->name }}</p>
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
</x-app-layout>
