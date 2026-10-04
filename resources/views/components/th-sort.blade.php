@props(['key', 'default' => false, 'defaultDir' => 'asc'])

{{-- Sortable table heading. Click to sort ascending, click again for descending. --}}
@php
    $cur = request('sort');
    $dir = request('dir');
    if (! $cur && $default) { $cur = $key; $dir = $defaultDir; }
    $dir    = $dir === 'desc' ? 'desc' : 'asc';
    $active = $cur === $key;
    $next   = $active ? ($dir === 'asc' ? 'desc' : 'asc') : 'asc';
    $url    = request()->fullUrlWithQuery(['sort' => $key, 'dir' => $next, 'page' => null]);
@endphp

<th scope="col" aria-sort="{{ $active ? ($dir === 'asc' ? 'ascending' : 'descending') : 'none' }}" {{ $attributes->merge(['class' => 'px-3 py-2.5 text-left']) }}>
    <a href="{{ $url }}" class="group inline-flex items-center gap-1 transition hover:text-slate-900 {{ $active ? 'text-slate-900' : '' }}" title="Sort by {{ strtolower(strip_tags($slot)) }}">
        {{ $slot }}
        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M8 9.5 12 5.5l4 4" class="{{ $active && $dir === 'asc' ? 'text-brand-700' : 'text-slate-300 group-hover:text-slate-400' }}"/>
            <path d="M8 14.5 12 18.5l4-4" class="{{ $active && $dir === 'desc' ? 'text-brand-700' : 'text-slate-300 group-hover:text-slate-400' }}"/>
        </svg>
    </a>
</th>
