@props(['user', 'size' => 'h-9 w-9', 'text' => 'text-sm'])

@if ($user->avatar)
    <img src="{{ $user->avatarUrl() }}" alt="{{ $user->name }}" loading="lazy"
         {{ $attributes->merge(['class' => "$size shrink-0 rounded-full object-cover ring-2 ring-white"]) }}>
@else
    <span {{ $attributes->merge(['class' => "$size $text flex shrink-0 items-center justify-center rounded-full bg-brand-800 font-bold uppercase text-white ring-2 ring-white"]) }}
          aria-label="{{ $user->name }}">{{ $user->initials() }}</span>
@endif
