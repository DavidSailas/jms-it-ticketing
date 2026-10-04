{{-- Open / directions / call shortcuts for one ticket. Pass $t. --}}
<a href="{{ route('tickets.show', $t) }}" class="rounded-lg bg-brand-800 px-3.5 py-2 text-xs font-semibold text-white transition hover:bg-brand-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2">Open ticket</a>
@if ($t->support_type === 'onsite' && $t->directionsUrl())
    <a href="{{ $t->directionsUrl() }}" target="_blank" rel="noopener" class="rounded-lg px-3.5 py-2 text-xs font-medium text-brand-700 ring-1 ring-brand-200 transition hover:bg-brand-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500">Directions</a>
@endif
@if ($t->contact_phone)
    <a href="tel:{{ preg_replace('/[^0-9+]/', '', $t->contact_phone) }}" class="rounded-lg px-3.5 py-2 text-xs font-medium text-slate-700 ring-1 ring-slate-300 transition hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500">Call {{ $t->contact_phone }}</a>
@endif
