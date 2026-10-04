<x-modal :name="'cancel-' . $ticket->id" maxWidth="md" focusable>
    <form method="POST" action="{{ route('tickets.cancel', $ticket) }}" class="p-6">
        @csrf
        <div class="flex items-start gap-4">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-rose-50 text-rose-600">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.3 3.9 2.4 18a2 2 0 0 0 1.7 3h15.8a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/></svg>
            </span>
            <div class="min-w-0">
                <h3 class="text-base font-semibold text-slate-900">Cancel this ticket?</h3>
                <p class="mt-1 text-sm text-slate-600"><span class="font-medium">{{ $ticket->ticket_no }}</span> &middot; {{ $ticket->subject }}</p>
                <p class="mt-2 text-sm text-slate-500">You can only cancel while the ticket hasn't been accepted or assigned. This can't be undone.</p>
            </div>
        </div>
        <div class="mt-4">
            <label class="mb-1 block text-sm font-medium text-slate-700">Reason <span class="font-normal text-slate-400">(optional)</span></label>
            <textarea name="reason" rows="2" maxlength="500" class="w-full rounded-lg border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500"></textarea>
        </div>
        <div class="mt-6 flex justify-end gap-2">
            <button type="button" x-on:click="$dispatch('close')" class="rounded-lg bg-white px-4 py-2 text-sm font-medium text-slate-700 ring-1 ring-slate-300 hover:bg-slate-50">Keep ticket</button>
            <button class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-700">Yes, cancel ticket</button>
        </div>
    </form>
</x-modal>
