<x-app-layout>
    <x-slot name="header">Edit {{ $ticket->ticket_no }}</x-slot>

    <div class="mx-auto max-w-4xl space-y-4">
        <div class="flex items-start gap-3 rounded-xl border border-brand-100 bg-brand-50 px-4 py-3 text-sm text-brand-800">
            <svg class="mt-0.5 h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="M12 8h.01M11 12h1v4h1"/></svg>
            <p>You are editing a ticket submitted by <strong>{{ $ticket->user->name }}</strong>. Changes to the schedule are sent to the requester and the assigned engineer.</p>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-8">
            @include('tickets._form', ['ticket' => $ticket])
        </div>
    </div>
</x-app-layout>
