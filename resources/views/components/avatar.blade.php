@props(['user', 'size' => 'h-9 w-9', 'text' => 'text-sm', 'zoom' => true])

{{-- With a photo the avatar opens the full-size viewer (layouts/partials/photo-viewer). Pass :zoom="false" where the avatar sits inside a link or button. --}}
@if ($user->avatar && $zoom)
    <button type="button" data-photo="{{ $user->avatarUrl() }}" data-photo-name="{{ $user->name }}"
            aria-label="View {{ $user->name }}'s photo" title="View photo" style="cursor:zoom-in"
            class="inline-flex shrink-0 rounded-full transition hover:opacity-90 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2">
        <img src="{{ $user->avatarUrl() }}" alt="" loading="lazy"
             {{ $attributes->merge(['class' => "$size shrink-0 rounded-full object-cover ring-2 ring-white"]) }}>
    </button>
@elseif ($user->avatar)
    <img src="{{ $user->avatarUrl() }}" alt="{{ $user->name }}" loading="lazy"
         {{ $attributes->merge(['class' => "$size shrink-0 rounded-full object-cover ring-2 ring-white"]) }}>
@else
    <span {{ $attributes->merge(['class' => "$size $text flex shrink-0 items-center justify-center rounded-full bg-brand-800 font-bold uppercase text-white ring-2 ring-white"]) }}
          aria-label="{{ $user->name }}">{{ $user->initials() }}</span>
@endif
