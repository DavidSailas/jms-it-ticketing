<x-app-layout>
    <x-slot name="header">Ticket board</x-slot>

    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
        <div class="inline-flex rounded-lg bg-white p-1 text-sm font-medium ring-1 ring-slate-200" aria-label="Ticket view">
            <a href="{{ route('tickets.index') }}" class="rounded-md px-3 py-1 text-slate-600 hover:bg-slate-50">List</a>
            <span class="rounded-md bg-brand-800 px-3 py-1 text-white" aria-current="page">Board</span>
        </div>
        <p class="flex items-center gap-1.5 text-xs text-slate-500" x-data="{ live: !!window.JMS_LIVE }" @live-status.window="live = $event.detail.connected" x-show="live" x-cloak>
            <span class="relative flex h-2 w-2"><span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span><span class="relative inline-flex h-2 w-2 rounded-full bg-emerald-500"></span></span> Live
        </p>
        <p class="text-xs text-slate-500">Drag a card to In progress, On hold or Resolved. On a phone, use the card's Move menu.</p>
    </div>

    <div x-data="ticketBoard(@js($cards), @js($config))" x-init="init()" @live-ticket.window="liveRefresh()" class="relative">
        {{-- Toast --}}
        <div x-show="toast" x-cloak x-transition role="status" aria-live="polite"
             class="fixed bottom-5 left-1/2 z-50 -translate-x-1/2 rounded-lg px-4 py-2.5 text-sm font-medium text-white shadow-lg"
             :class="toastError ? 'bg-red-600' : 'bg-slate-800'" x-text="toast"></div>

        <div class="-mx-4 flex gap-4 overflow-x-auto px-4 pb-4 sm:mx-0 sm:px-0">
            <template x-for="col in cols" :key="col.status">
                <section x-data="{ status: col.status, label: col.label }" class="flex w-72 shrink-0 flex-col rounded-2xl border bg-slate-50 transition"
                         :class="isTarget(status) ? (over === status ? 'border-brand-500 bg-brand-50 ring-2 ring-brand-200' : 'border-brand-300 border-dashed') : 'border-slate-200'"
                         @dragover.prevent="dragOver(status)" @dragleave="if (over === status) over = null" @drop.prevent="drop(status)"
                         :aria-label="label + ' column'">
                    <header class="flex items-center justify-between px-4 py-3">
                        <h3 class="text-sm font-semibold text-slate-700" x-text="label"></h3>
                        <span class="rounded-full bg-white px-2 py-0.5 text-xs font-semibold text-slate-500 ring-1 ring-slate-200" x-text="inColumn(status).length"></span>
                    </header>
                    <div class="flex min-h-[6rem] flex-1 flex-col gap-3 px-3 pb-3">
                        <template x-for="c in inColumn(status)" :key="c.id">
                            <article class="rounded-xl border border-slate-200 bg-white p-3 shadow-sm transition"
                                     :class="[c.movable ? 'cursor-grab active:cursor-grabbing' : '', dragging && dragging.id === c.id ? 'opacity-40' : '', busy === c.id ? 'animate-pulse' : '']"
                                     :draggable="c.movable ? 'true' : 'false'"
                                     @dragstart="dragStart($event, c)" @dragend="dragEnd()">
                                <div class="flex items-center gap-2">
                                    <span class="h-2 w-2 shrink-0 rounded-full" :class="dot(c.priority)" :title="c.priority + ' priority'"></span>
                                    <a :href="c.url" class="truncate text-xs font-semibold text-brand-700 hover:underline" x-text="c.no"></a>
                                    <span class="ml-auto text-[11px] capitalize text-slate-400" x-text="c.priority"></span>
                                </div>
                                <a :href="c.url" class="mt-1.5 block text-sm font-medium leading-snug text-slate-800 hover:text-brand-700" x-text="c.subject"></a>
                                <p class="mt-1 truncate text-xs text-slate-500" x-show="c.company" x-text="c.company"></p>
                                <p class="truncate text-xs text-slate-400" x-text="c.requester ? 'by ' + c.requester : ''"></p>
                                <div class="mt-2 flex items-center justify-between gap-2 text-xs">
                                    <span class="truncate text-slate-600" x-text="c.assignee || 'Unassigned'" :class="c.assignee ? '' : 'font-semibold text-violet-600'"></span>
                                    <span x-show="c.due" class="shrink-0 tabular-nums" :class="sla(c).cls" x-text="sla(c).text"></span>
                                </div>
                                <label class="mt-2 block" x-show="c.movable">
                                    <span class="sr-only" x-text="'Move ' + c.no"></span>
                                    <select class="w-full rounded-md border-slate-200 py-1 text-xs text-slate-500 focus:border-brand-500 focus:ring-brand-500"
                                            @change="pick(c, $event.target.value); $event.target.value = ''">
                                        <option value="">Move to...</option>
                                        <template x-for="t in targetsFor(c)" :key="t"><option :value="t" x-text="cfg.columns[t]"></option></template>
                                    </select>
                                </label>
                                <a x-show="!c.movable && c.status === 'open'" :href="c.url" class="mt-2 block text-xs font-semibold text-violet-600 hover:underline">Open to assign an engineer</a>
                            </article>
                        </template>
                        <p class="px-1 py-4 text-center text-xs text-slate-400" x-show="inColumn(status).length === 0">No tickets</p>
                        <p class="px-1 text-center text-[11px] text-slate-400" x-show="status === 'resolved'" x-text="'Resolved in the last ' + cfg.resolvedDays + ' days'"></p>
                    </div>
                </section>
            </template>
        </div>

        {{-- Resolution note (needed to resolve) --}}
        <div x-show="resolving" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4" @keydown.escape.window="cancelResolve()">
            <form @submit.prevent="submitResolve()" class="w-full max-w-md space-y-3 rounded-2xl bg-white p-5 shadow-xl" role="dialog" aria-modal="true" aria-label="Resolve ticket">
                <h3 class="font-semibold text-brand-800" x-text="resolving ? 'Resolve ' + resolving.no : ''"></h3>
                <label class="block text-sm font-medium text-slate-700">What did you do to fix it?</label>
                <textarea x-ref="note" x-model="note" rows="4" maxlength="3000" class="w-full rounded-lg border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500" placeholder="Describe the fix. The requester will see this."></textarea>
                <p class="text-sm text-red-600" x-show="noteError" x-text="noteError"></p>
                <div class="flex justify-end gap-2">
                    <button type="button" @click="cancelResolve()" class="rounded-lg px-4 py-2 text-sm text-slate-600 ring-1 ring-slate-300 hover:bg-slate-50">Cancel</button>
                    <button class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Mark as resolved</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('ticketBoard', (cards, cfg) => ({
                cards, cfg, dragging: null, over: null, busy: null, now: Date.now(),
                resolving: null, note: '', noteError: '', toast: '', toastError: false, toastTimer: null,
                get cols() { return Object.entries(this.cfg.columns).map(([status, label]) => ({ status, label })); },
                init() {
                    setInterval(() => { this.now = Date.now(); }, 30000);
                    // Without websockets the board still catches up every minute.
                    setInterval(() => { if (!document.hidden) this.refreshCards(); }, 60000);
                    document.addEventListener('visibilitychange', () => { if (!document.hidden) this.refreshCards(); });
                },
                liveTimer: null,
                liveRefresh() { clearTimeout(this.liveTimer); this.liveTimer = setTimeout(() => this.refreshCards(), 400); },
                /** Re-read the board, but never while someone is mid-drag or writing a resolution. */
                async refreshCards() {
                    if (this.dragging || this.resolving || this.busy) return;
                    try {
                        const res = await fetch(this.cfg.cardsUrl, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
                        if (!res.ok || this.dragging || this.resolving || this.busy) return;
                        this.cards = (await res.json()).cards;
                    } catch (e) { /* offline: keep what is shown */ }
                },
                inColumn(status) { return this.cards.filter((c) => c.status === status); },
                targetsFor(c) { return c.movable ? ['in_progress', 'on_hold', 'resolved'].filter((t) => t !== c.status) : []; },
                isTarget(status) { return this.dragging !== null && this.targetsFor(this.dragging).includes(status); },
                dot(p) { return { low: 'bg-slate-300', medium: 'bg-sky-400', high: 'bg-orange-500', critical: 'bg-red-600' }[p] || 'bg-slate-300'; },
                sla(c) {
                    const left = c.due - this.now, t = Math.floor(Math.abs(left) / 60000);
                    const text = t >= 1440 ? Math.floor(t / 1440) + 'd' : (t >= 60 ? Math.floor(t / 60) + 'h ' + (t % 60) + 'm' : t + 'm');
                    if (left < 0) return { text: 'Overdue ' + text, cls: 'font-semibold text-red-600' };
                    return { text: 'Due ' + text, cls: left < 7200000 ? 'font-medium text-orange-600' : 'text-slate-500' };
                },
                say(msg, error = false) {
                    this.toast = msg; this.toastError = error; clearTimeout(this.toastTimer);
                    this.toastTimer = setTimeout(() => { this.toast = ''; }, 3500);
                },
                dragStart(e, c) {
                    if (!c.movable) { e.preventDefault(); return; }
                    this.dragging = c; e.dataTransfer.effectAllowed = 'move'; e.dataTransfer.setData('text/plain', String(c.id));
                },
                dragEnd() { this.dragging = null; this.over = null; },
                dragOver(status) { if (this.isTarget(status)) this.over = status; },
                drop(status) {
                    const c = this.dragging; this.dragEnd();
                    if (!c) return;
                    this.pick(c, status);
                },
                /** Called by a drop or the Move menu. */
                pick(c, status) {
                    if (!status || status === c.status) return;
                    if (!this.targetsFor(c).includes(status)) {
                        this.say(c.status === 'open' ? 'Assign an engineer first: open the ticket and use "Accept & assign".' : 'That move is not allowed.', true);
                        return;
                    }
                    if (status === 'resolved') {
                        this.resolving = c; this.note = ''; this.noteError = '';
                        this.$nextTick(() => this.$refs.note && this.$refs.note.focus());
                        return;
                    }
                    this.send(c, status);
                },
                cancelResolve() { this.resolving = null; },
                submitResolve() {
                    if (this.note.trim().length < 10) { this.noteError = 'Please add a little more detail (at least 10 characters).'; return; }
                    const c = this.resolving;
                    this.send(c, 'resolved', this.note.trim()).then((ok) => { if (ok) this.resolving = null; });
                },
                async send(c, status, resolution = null) {
                    this.busy = c.id;
                    try {
                        const res = await fetch(this.cfg.moveUrl + '/' + c.id + '/move', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json', 'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                            },
                            body: JSON.stringify({ status, resolution }),
                        });
                        const json = await res.json().catch(() => ({}));
                        if (!res.ok) {
                            const msg = json.errors ? Object.values(json.errors).flat()[0] : (json.message || 'Could not move the ticket.');
                            if (status === 'resolved') this.noteError = msg; else this.say(msg, true);
                            return false;
                        }
                        const i = this.cards.findIndex((x) => x.id === c.id);
                        if (i > -1) this.cards.splice(i, 1, json.card);
                        this.say(json.card.no + ' moved to ' + this.cfg.columns[json.card.status] + '.');
                        return true;
                    } catch (e) {
                        this.say('Network problem. The ticket was not moved.', true);
                        return false;
                    } finally { this.busy = null; }
                },
            }));
        });
    </script>
</x-app-layout>
