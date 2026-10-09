{{-- Ctrl+K search dialog. Logic: resources/js/search.js. Expects $searchConfig (url, pages, more) from the layout. --}}
<div x-data="commandPalette(@js($searchConfig))" x-show="open" x-cloak @keydown="open && onKey($event)"
     class="fixed inset-0 z-[70] flex items-start justify-center p-3 pt-[7vh] sm:p-4 sm:pt-[12vh]"
     role="dialog" aria-modal="true" aria-label="Search">

    <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-[2px]" @click="hide()" aria-hidden="true"
         x-show="open" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"></div>

    <div class="relative flex max-h-[78vh] w-full max-w-xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl ring-1 ring-slate-900/10"
         x-show="open" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="translate-y-2 scale-[0.98] opacity-0" x-transition:enter-end="translate-y-0 scale-100 opacity-100"
         x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">

        {{-- Search box --}}
        <div class="flex items-center gap-3 border-b border-slate-100 px-4">
            <svg class="h-5 w-5 shrink-0 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
            <input x-ref="input" x-model="q" @input="typed()" type="text" autocomplete="off" autocorrect="off" spellcheck="false" maxlength="60"
                   role="combobox" aria-autocomplete="list" aria-controls="palette-list" :aria-expanded="flat.length > 0"
                   :aria-activedescendant="flat[active] ? flat[active].dom : null" aria-label="Search tickets, people and companies"
                   placeholder="Search tickets, people, companies..."
                   class="h-14 w-full border-0 bg-transparent text-base text-slate-900 placeholder-slate-400 focus:outline-none focus:ring-0">
            <svg x-show="loading" x-cloak class="h-4 w-4 shrink-0 animate-spin text-brand-600" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity=".2" stroke-width="3"/><path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>
            <button type="button" @click="hide()" class="shrink-0 rounded-md border border-slate-200 px-1.5 py-0.5 text-[11px] font-medium text-slate-400 transition hover:bg-slate-50 hover:text-slate-600">Esc</button>
        </div>

        <p class="sr-only" role="status" aria-live="polite" x-text="status"></p>

        {{-- Results --}}
        <div id="palette-list" role="listbox" aria-label="Results" class="min-h-0 flex-1 overflow-y-auto overscroll-contain py-2">

            <div x-show="searching && loading && !server.tickets.length && !server.people.length && !server.companies.length" x-cloak class="space-y-2 px-4 py-2" aria-hidden="true">
                <template x-for="n in 3" :key="n">
                    <div class="flex animate-pulse items-center gap-3"><div class="h-9 w-9 rounded-lg bg-slate-100"></div><div class="flex-1 space-y-1.5"><div class="h-3 w-2/3 rounded bg-slate-100"></div><div class="h-2.5 w-1/3 rounded bg-slate-100"></div></div></div>
                </template>
            </div>

            <div x-show="failed" x-cloak class="mx-4 mb-1 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800">Search is not available right now. You can still use the shortcuts below.</div>

            <div x-show="nothing" x-cloak class="px-4 pb-2 pt-3 text-center">
                <p class="text-sm font-medium text-slate-700">No matches for "<span x-text="term"></span>"</p>
                <p class="mt-0.5 text-xs text-slate-500">Try a ticket number, a word from the subject, or a person's name.</p>
            </div>

            <template x-for="section in sections" :key="section.key">
                <div role="group" :aria-label="section.label">
                    <div class="flex items-center justify-between px-5 pb-1 pt-3 text-xs font-semibold text-slate-400">
                        <span x-text="section.label"></span>
                        <button type="button" x-show="section.key === 'recent'" @click="clearRecent()" class="rounded px-1 text-xs font-medium text-slate-400 hover:text-slate-600">Clear</button>
                    </div>

                    <template x-for="item in section.items" :key="item.dom">
                        <a :id="item.dom" :href="item.url" role="option" :aria-selected="active === item.idx" tabindex="-1"
                           @mouseenter="active = item.idx" @click="choose(item)"
                           class="mx-2 flex items-center gap-3 rounded-lg px-3 py-2 text-left transition-colors"
                           :class="active === item.idx ? 'bg-brand-50' : ''">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg"
                                  :class="active === item.idx ? 'bg-white text-brand-700 shadow-sm' : 'bg-slate-100 text-slate-500'">
                                <svg class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" x-html="item.icon || icon(item.kind)"></svg>
                            </span>

                            <span class="min-w-0 flex-1">
                                <span class="flex items-baseline gap-2">
                                    <span x-show="item.no" class="shrink-0 font-mono text-[11px] text-slate-400" x-text="item.no"></span>
                                    <span class="truncate text-sm font-medium text-slate-800">
                                        <template x-for="(part, i) in marks(item.title)" :key="i"><span :class="part.hit ? 'rounded-sm bg-amber-100 text-slate-900' : ''" x-text="part.t"></span></template>
                                    </span>
                                </span>
                                <span x-show="item.sub" class="block truncate text-xs text-slate-500" x-text="item.sub"></span>
                            </span>

                            <span x-show="item.kind === 'ticket'" class="flex shrink-0 items-center gap-1.5 text-xs text-slate-500">
                                <span class="h-2 w-2 rounded-full" :style="'background:' + item.color"></span><span x-text="item.status_label"></span>
                            </span>
                            <kbd x-show="item.kind !== 'ticket' && active === item.idx" class="shrink-0 rounded border border-slate-200 bg-white px-1.5 py-0.5 font-sans text-[11px] text-slate-400">Enter</kbd>
                        </a>
                    </template>
                </div>
            </template>
        </div>

        {{-- Key hints (desktop) --}}
        <div class="hidden items-center gap-4 border-t border-slate-100 bg-slate-50 px-4 py-2 text-[11px] text-slate-400 sm:flex" aria-hidden="true">
            <span><kbd class="rounded border border-slate-200 bg-white px-1 font-sans">&uarr;</kbd> <kbd class="rounded border border-slate-200 bg-white px-1 font-sans">&darr;</kbd> to move</span>
            <span><kbd class="rounded border border-slate-200 bg-white px-1 font-sans">Enter</kbd> to open</span>
            <span><kbd class="rounded border border-slate-200 bg-white px-1 font-sans">/</kbd> opens this from anywhere</span>
        </div>
    </div>
</div>
