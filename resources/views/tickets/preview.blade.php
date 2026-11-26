<x-app-layout>
    <x-slot name="header">{{ $ticket->ticket_no }}</x-slot>

    @php
        $sla  = $ticket->slaBadge();
        $card = 'rounded-2xl border border-slate-200 bg-white shadow-sm';
        $submitted = $ticket->attachments->whereNull('ticket_comment_id');
    @endphp

    <div class="mx-auto max-w-5xl space-y-4">
        <a href="{{ route('tickets.available') }}" class="inline-flex items-center gap-1 text-sm font-semibold text-brand-700 hover:underline">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 6-6 6 6 6"/></svg>
            Available tickets
        </a>

        {{-- Read-only notice --}}
        <div class="flex gap-3 rounded-2xl border border-violet-200 bg-violet-50 p-4 text-sm leading-relaxed text-violet-900" role="status">
            <svg class="mt-0.5 h-5 w-5 shrink-0 text-violet-600" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg>
            <p><strong>View only.</strong> This ticket is waiting for an admin to accept it and assign an engineer. You cannot accept it or change anything here.</p>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <div class="{{ $card }} p-5 sm:p-6">
                    <div class="mb-3 flex flex-wrap items-center gap-2">
                        <span class="rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset {{ $ticket->statusClasses() }}">{{ $ticket->statusLabel() }}</span>
                        <span class="rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset {{ $ticket->priorityClasses() }}">{{ $ticket->priorityLabel() }} priority</span>
                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs text-slate-600">{{ $ticket->category }}</span>
                        @if ($ticket->supportTypeLabel()) <span class="rounded-full bg-brand-50 px-2.5 py-1 text-xs font-medium text-brand-700">{{ $ticket->supportTypeLabel() }}</span> @endif
                        <x-jms-requested-badge :ticket="$ticket" />
                    </div>

                    <h2 class="text-xl font-bold text-brand-800">{{ $ticket->subject }}</h2>
                    <p class="mt-1 text-xs text-slate-500">Submitted {{ $ticket->created_at->format('M d, Y h:i A') }} &middot; {{ $ticket->created_at->diffForHumans() }}</p>

                    <div class="mt-6 border-t border-slate-100 pt-5">
                        <h3 class="mb-2 text-sm font-semibold text-slate-700">Description</h3>
                        <p class="whitespace-pre-line text-sm leading-relaxed text-slate-700">{{ $ticket->description }}</p>
                    </div>

                    @if ($submitted->isNotEmpty())
                        <div class="mt-5 border-t border-slate-100 pt-5">
                            <h3 class="mb-2 text-sm font-semibold text-slate-700">Attachments <span class="font-normal text-slate-400">({{ $submitted->count() }})</span></h3>
                            <ul class="space-y-1.5 text-sm text-slate-600">
                                @foreach ($submitted as $a)
                                    <li class="flex items-center gap-2"><svg class="h-4 w-4 shrink-0 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12.5 12.5 21a5.5 5.5 0 0 1-8-8L13 4.5a3.7 3.7 0 0 1 5.3 5.3L10 18a1.8 1.8 0 0 1-2.6-2.6L15 8"/></svg><span class="truncate">{{ $a->original_name }}</span></li>
                                @endforeach
                            </ul>
                            <p class="mt-2 text-xs text-slate-400">Files can be opened once the ticket is assigned to you.</p>
                        </div>
                    @endif
                </div>
            </div>

            <div class="space-y-6">
                <div class="{{ $card }} p-5">
                    <h3 class="mb-3 font-semibold text-brand-800">At a glance</h3>
                    <dl class="space-y-3 text-sm">
                        <div class="flex items-center gap-3">
                            <x-avatar :user="$ticket->user" size="h-9 w-9" />
                            <div class="min-w-0">
                                <dt class="text-xs text-slate-400">Requested by</dt>
                                <dd class="truncate font-medium text-slate-800">{{ $ticket->user->name }}</dd>
                                @if ($ticket->user->company) <dd class="truncate text-xs text-slate-500">{{ $ticket->user->company }}</dd> @endif
                            </div>
                        </div>
                        <div><dt class="text-xs text-slate-400">When</dt><dd class="font-medium text-slate-800">{{ $ticket->whenLabel() }}</dd></div>
                        @if (trim((string) $ticket->location) !== '')
                            <div><dt class="text-xs text-slate-400">Location</dt><dd class="font-medium text-slate-800">{{ $ticket->location }}</dd></div>
                        @endif
                        @if ($sla)
                            <div><dt class="text-xs text-slate-400">Response target</dt><dd class="font-medium {{ $sla[1] }}">{{ $sla[0] }}</dd></div>
                        @endif
                    </dl>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5 text-sm text-slate-600">
                    <h3 class="font-semibold text-slate-800">What happens next</h3>
                    <p class="mt-1 leading-relaxed">An admin reviews the ticket and assigns an IT engineer. If that is you, you will get a notification and the ticket appears in My Assignments.</p>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
