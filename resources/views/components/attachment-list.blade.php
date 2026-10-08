{{-- Thumbnails for pictures, a file row for everything else. Each one opens through the authorised download route. --}}
@props(['ticket', 'attachments'])
@php
    $me     = auth()->user();
    $images = $attachments->filter(fn ($a) => $a->isImage());
    $others = $attachments->reject(fn ($a) => $a->isImage());
    $remove = function ($a) use ($ticket, $me) {
        return $a->canBeRemovedBy($me) ? route('tickets.attachments.destroy', [$ticket, $a]) : null;
    };
@endphp

@if ($attachments->isNotEmpty())
    <div {{ $attributes->merge(['class' => 'space-y-3']) }}>
        @if ($images->isNotEmpty())
            <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                @foreach ($images as $a)
                    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
                        <a href="{{ route('tickets.attachments.show', [$ticket, $a]) }}" target="_blank" rel="noopener" class="block bg-slate-100">
                            <img src="{{ route('tickets.attachments.show', [$ticket, $a]) }}" alt="{{ $a->original_name }}" loading="lazy" class="h-28 w-full object-cover">
                        </a>
                        <div class="flex items-center justify-between gap-2 px-2 py-1.5 text-xs">
                            <span class="min-w-0 truncate text-slate-600" title="{{ $a->original_name }}">{{ $a->original_name }}</span>
                            @if ($url = $remove($a))
                                <form method="POST" action="{{ $url }}" onsubmit="return confirm('Remove this attachment?')">
                                    @csrf @method('DELETE')
                                    <button class="shrink-0 rounded p-0.5 text-slate-400 hover:text-red-600" title="Remove" aria-label="Remove {{ $a->original_name }}">
                                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg>
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        @if ($others->isNotEmpty())
            <ul class="space-y-1.5">
                @foreach ($others as $a)
                    <li class="flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">
                        <svg class="h-4 w-4 shrink-0 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M7 3h8l4 4v14H7z"/><path d="M15 3v4h4M10 12h6M10 16h6"/></svg>
                        <a href="{{ route('tickets.attachments.show', [$ticket, $a]) }}" class="min-w-0 flex-1 truncate font-medium text-brand-700 hover:underline" title="Download {{ $a->original_name }}">{{ $a->original_name }}</a>
                        <span class="shrink-0 text-xs text-slate-400">{{ strtoupper($a->extension()) }} &middot; {{ $a->sizeLabel() }}</span>
                        @if ($url = $remove($a))
                            <form method="POST" action="{{ $url }}" onsubmit="return confirm('Remove this attachment?')">
                                @csrf @method('DELETE')
                                <button class="shrink-0 rounded p-1 text-slate-400 hover:bg-red-50 hover:text-red-600" title="Remove" aria-label="Remove {{ $a->original_name }}">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg>
                                </button>
                            </form>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
@endif
