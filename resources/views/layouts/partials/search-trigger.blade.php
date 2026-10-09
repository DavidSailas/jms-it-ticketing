{{-- Header button that opens the Ctrl+K search (the dialog itself is command-palette.blade.php at the end of the page) --}}
<div x-data="{ mac: /Mac|iPhone|iPad/.test(navigator.platform || '') }" class="flex items-center">
    <button type="button" @click="$dispatch('open-search')" aria-label="Search" aria-keyshortcuts="Control+K Meta+K"
            class="hidden items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 py-1.5 pl-3 pr-2 text-sm text-slate-500 transition hover:border-slate-300 hover:bg-white focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 md:flex lg:w-60">
        <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
        <span class="hidden flex-1 text-left lg:block">Search...</span>
        <kbd class="hidden rounded border border-slate-200 bg-white px-1.5 py-0.5 font-sans text-[11px] font-medium text-slate-400 lg:block" x-text="mac ? 'Cmd K' : 'Ctrl K'"></kbd>
    </button>
    <button type="button" @click="$dispatch('open-search')" aria-label="Search"
            class="flex h-10 w-10 items-center justify-center rounded-full text-slate-500 transition hover:bg-slate-100 hover:text-brand-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 md:hidden">
        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
    </button>
</div>
