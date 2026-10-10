@props(['user'])
{{-- Marks a request that came from a partner company's own admin, so staff can spot it in lists and on the ticket. --}}
@if ($user && $user->role === 'admin' && $user->company_id)
    <span {{ $attributes->merge(['class' => 'inline-flex shrink-0 items-center rounded-full bg-violet-50 px-2 py-0.5 text-[11px] font-semibold text-violet-700 ring-1 ring-inset ring-violet-200']) }} title="Requested by the company's admin">Company admin</span>
@endif
