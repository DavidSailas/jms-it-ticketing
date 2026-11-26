<x-app-layout>
    <x-slot name="header">Available tickets</x-slot>

    @php($accent = ['low' => 'bg-slate-300', 'medium' => 'bg-sky-400', 'high' => 'bg-orange-500', 'critical' => 'bg-red-600'])

    <div class="mx-auto max-w-5xl space-y-4">
        {{-- Explains what this page is, and what an engineer can and cannot do here --}}
        <div class="flex gap-3 rounded-2xl border border-sky-200 bg-sky-50 p-4 text-sm leading-relaxed text-sky-900">
            <svg class="mt-0.5 h-5 w-5 shrink-0 text-sky-600" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/></svg>
            <p>
                These tickets are <strong>waiting for an admin to accept and assign them</strong>. You can read the details so you know what is coming,
                but only an admin can accept a ticket. Once one is assigned to you, it moves to <a href="{{ route('tickets.index') }}" class="font-semibold underline">My Assignments</a>.
            </p>
        </div>

        <p class="px-1 text-xs text-slate-500">
            @if ($tickets->total() > 0)
                <span class="font-semibold text-slate-700">{{ number_format($tickets->total()) }}</span> {{ \Illuminate\Support\Str::plural('ticket', $tickets->total()) }} waiting &middot; most urgent first
            @endif
        </p>

        <div class="space-y-3">
            @forelse ($tickets as $t)
                @php($sla = $t->slaBadge())
                <article class="group relative overflow-hidden rounded-2xl border border-slate-200 bg-white p-4 pl-5 shadow-sm transition hover:border-brand-300 hover:shadow-md sm:p-5 sm:pl-6">
                    <span class="absolute inset-y-0 left-0 w-1.5 {{ $accent[$t->priority] ?? 'bg-slate-300' }}" aria-hidden="true"></span>

                    <div class="flex items-center justify-between gap-2 text-[11px] text-slate-400">
                        <span class="font-mono">{{ $t->ticket_no }}</span>
                        <span title="{{ $t->created_at->format('M d, Y h:i A') }}">{{ $t->created_at->diffForHumans() }}</span>
                    </div>

                    <a href="{{ route('tickets.preview', $t->id) }}" class="mt-1 line-clamp-2 block text-[15px] font-semibold leading-snug text-slate-900 after:absolute after:inset-0 after:content-[''] group-hover:text-brand-800 sm:text-base">{{ $t->subject }}</a>

                    <div class="mt-2.5 flex flex-wrap items-center gap-1.5">
                        <x-ticket-pill :ticket="$t" type="priority" />
                        <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs text-slate-600">{{ $t->category }}</span>
                        @if ($t->supportTypeLabel()) <span class="rounded-full bg-brand-50 px-2.5 py-0.5 text-xs font-medium text-brand-700">{{ $t->supportTypeLabel() }}</span> @endif
                        @if ($t->scheduledShort()) <span class="rounded-full bg-indigo-50 px-2.5 py-0.5 text-xs font-medium text-indigo-700">Scheduled {{ $t->scheduledShort() }}</span> @endif
                        <x-jms-requested-badge :ticket="$t" />
                    </div>

                    <div class="mt-3 flex items-center justify-between gap-3 border-t border-slate-100 pt-3 text-xs">
                        <span class="flex min-w-0 items-center gap-2">
                            <x-avatar :user="$t->user" size="h-7 w-7" text="text-[10px]" />
                            <span class="min-w-0">
                                <span class="block truncate font-medium text-slate-800">{{ $t->user->name }}</span>
                                @if ($t->user->company) <span class="block truncate text-slate-500">{{ $t->user->company }}</span> @endif
                            </span>
                        </span>
                        <span class="flex shrink-0 items-center gap-3">
                            @if ($sla) <span class="hidden sm:inline {{ $sla[1] }}">{{ $sla[0] }}</span> @endif
                            <span class="inline-flex items-center gap-1 font-semibold text-brand-700">
                                View
                                <svg class="h-4 w-4 transition group-hover:translate-x-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>
                            </span>
                        </span>
                    </div>
                </article>
            @empty
                <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-16 text-center">
                    <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-emerald-50 text-emerald-600">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 5 5L20 7"/></svg>
                    </span>
                    <p class="mt-3 font-semibold text-slate-800">Nothing is waiting right now</p>
                    <p class="mt-1 text-sm text-slate-500">New tickets appear here until an admin accepts them.</p>
                </div>
            @endforelse
        </div>

        @if ($tickets->hasPages()) <div class="pt-1">{{ $tickets->links() }}</div> @endif
    </div>
</x-app-layout>
