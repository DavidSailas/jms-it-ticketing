@props(['ticket'])
{{-- The company could not solve this one and asked JMS support to take over; shows until JMS accepts it. --}}
@if ($ticket->isAskingForJms())
    <span {{ $attributes->merge(['class' => 'inline-flex shrink-0 items-center gap-1 rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-semibold text-amber-800 ring-1 ring-inset ring-amber-300']) }} title="The company asked JMS support to take over this ticket">
        <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 21V4M5 4h11l-2 4 2 4H5"/></svg>
        JMS requested
    </span>
@endif
