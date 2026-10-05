{{-- Full-size profile photo viewer. Any element with data-photo="url" and data-photo-name="name" opens it (see <x-avatar>). --}}
<div x-data="{ open: false, src: '', name: '' }"
     x-effect="document.body.classList.toggle('overflow-hidden', open)"
     @click.capture.window="const el = $event.target.closest('[data-photo]');
                            if (el) { $event.preventDefault(); $event.stopPropagation(); src = el.dataset.photo; name = el.dataset.photoName || ''; open = true; $nextTick(() => $refs.close.focus()); }"
     @keydown.escape.window="open = false"
     x-show="open" x-cloak
     x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
     role="dialog" aria-modal="true" aria-label="Profile photo"
     @click="open = false"
     style="position:fixed;top:0;right:0;bottom:0;left:0;z-index:80;background-color:rgba(15,23,42,.92)"
     class="flex flex-col items-center justify-center p-4 sm:p-8">

    <button type="button" x-ref="close" @click.stop="open = false" aria-label="Close photo"
            style="position:absolute;top:1rem;right:1rem" class="flex h-10 w-10 items-center justify-center rounded-full bg-white/10 text-white transition hover:bg-white/20 focus:outline-none focus-visible:ring-2 focus-visible:ring-white sm:right-6 sm:top-6">
        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
    </button>

    <template x-if="open">
        <img :src="src" :alt="name" @click.stop style="max-width:100%;max-height:80vh;max-height:80dvh" class="rounded-2xl bg-white object-contain shadow-2xl">
    </template>
    <p x-text="name" class="mt-4 max-w-full truncate text-sm font-medium text-white"></p>
</div>
