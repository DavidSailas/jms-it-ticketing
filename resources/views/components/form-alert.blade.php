@props(['type' => 'success', 'title' => null])

@php
    $styles = [
        'success' => ['border-emerald-200 bg-emerald-50 text-emerald-800', 'text-emerald-500', '<circle cx="12" cy="12" r="9"/><path d="M8.5 12.5l2.5 2.5 4.5-5"/>'],
        'error'   => ['border-red-200 bg-red-50 text-red-800',             'text-red-500',     '<circle cx="12" cy="12" r="9"/><path d="M12 8v4.5M12 16h.01"/>'],
        'info'    => ['border-brand-200 bg-brand-50 text-brand-800',       'text-brand-500',   '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/>'],
    ][$type];
@endphp

<div role="{{ $type === 'error' ? 'alert' : 'status' }}" {{ $attributes->merge(['class' => "flex gap-3 rounded-xl border px-4 py-3 text-sm {$styles[0]}"]) }}>
    <svg class="mt-0.5 h-5 w-5 shrink-0 {{ $styles[1] }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $styles[2] !!}</svg>
    <div class="min-w-0">
        @if ($title) <p class="font-semibold">{{ $title }}</p> @endif
        <div class="{{ $title ? 'mt-0.5' : '' }}">{{ $slot }}</div>
    </div>
</div>
