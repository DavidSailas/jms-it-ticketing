@props(['name', 'value' => null, 'now', 'maxDays' => 90, 'lead' => 30, 'invalid' => false])

{{-- Custom date + time picker. Submits "YYYY-MM-DDTHH:mm" in a hidden input named $name.
     $now is the server's current wall-clock time ("Y-m-d H:i") so the rules match the server's timezone. --}}
@once
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('dateTimePicker', (cfg) => ({
                open: false,
                value: cfg.value || '',
                selDate: cfg.value ? cfg.value.slice(0, 10) : null,
                selTime: cfg.value ? cfg.value.slice(11, 16) : null,
                view: { y: 0, m: 0 },
                weekdays: ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'],
                months: ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
                slots: [],
                presets: [],

                init() {
                    const base = this.selDate || cfg.now.slice(0, 10);
                    const [y, m] = base.split('-').map(Number);
                    this.view = { y, m: m - 1 };

                    for (let h = 7; h <= 20; h++) {
                        this.slots.push(this.pad(h) + ':00');
                        if (h < 20) this.slots.push(this.pad(h) + ':30');
                    }

                    const today = this.wall(cfg.now);
                    const dayKey = (add) => { const d = new Date(today); d.setDate(d.getDate() + add); return this.key(d.getFullYear(), d.getMonth(), d.getDate()); };
                    let toMonday = ((8 - today.getDay()) % 7) || 7;
                    if (toMonday === 1) toMonday = 8;   // "tomorrow" is already a quick pick
                    this.presets = [
                        { label: 'Tomorrow, 9:00 AM', k: dayKey(1), t: '09:00' },
                        { label: 'Tomorrow, 2:00 PM', k: dayKey(1), t: '14:00' },
                        { label: 'Next Monday, 9:00 AM', k: dayKey(toMonday), t: '09:00' },
                    ];
                },

                pad(n) { return String(n).padStart(2, '0'); },
                key(y, m, d) { return y + '-' + this.pad(m + 1) + '-' + this.pad(d); },
                parts(k) { return k.split('-').map(Number); },
                wall(str) {
                    const [d, t] = str.replace('T', ' ').split(' ');
                    const [y, m, day] = d.split('-').map(Number);
                    const [h, mi] = (t || '00:00').split(':').map(Number);
                    return new Date(y, m - 1, day, h, mi);
                },
                earliest() { return new Date(this.wall(cfg.now).getTime() + cfg.lead * 60000); },
                latest() { const d = this.wall(cfg.now); d.setDate(d.getDate() + cfg.maxDays); return d; },

                dayDisabled(k) {
                    const [y, m, d] = this.parts(k);
                    // A day is usable while its last bookable slot (8:00 PM) is still ahead.
                    return new Date(y, m - 1, d, 20, 0) < this.earliest() || new Date(y, m - 1, d, 0, 0) > this.latest();
                },
                slotDisabled(t, k) {
                    k = k || this.selDate;
                    if (!k) return true;
                    const [y, m, d] = this.parts(k);
                    const [h, mi] = t.split(':').map(Number);
                    const dt = new Date(y, m - 1, d, h, mi);
                    return dt < this.earliest() || dt > this.latest();
                },

                cells() {
                    const { y, m } = this.view;
                    const out = [];
                    for (let i = 0; i < new Date(y, m, 1).getDay(); i++) out.push({ blank: true });
                    const todayKey = cfg.now.slice(0, 10);
                    for (let d = 1; d <= new Date(y, m + 1, 0).getDate(); d++) {
                        const k = this.key(y, m, d);
                        out.push({ blank: false, d, k, disabled: this.dayDisabled(k), today: k === todayKey, selected: k === this.selDate });
                    }
                    return out;
                },

                monthLabel() { return this.months[this.view.m] + ' ' + this.view.y; },
                canPrev() { const e = this.earliest(); return this.view.y * 12 + this.view.m > e.getFullYear() * 12 + e.getMonth(); },
                canNext() { const l = this.latest(); return this.view.y * 12 + this.view.m < l.getFullYear() * 12 + l.getMonth(); },
                shift(n) {
                    let m = this.view.m + n, y = this.view.y;
                    if (m < 0) { m = 11; y--; } else if (m > 11) { m = 0; y++; }
                    this.view = { y, m };
                },

                fmtTime(t) {
                    const [h, mi] = t.split(':').map(Number);
                    return ((h % 12) || 12) + ':' + this.pad(mi) + ' ' + (h < 12 ? 'AM' : 'PM');
                },
                display() {
                    if (!this.selDate) return '';
                    const [y, m, d] = this.parts(this.selDate);
                    const ds = new Date(y, m - 1, d).toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' });
                    return this.selTime ? ds + ' \u00b7 ' + this.fmtTime(this.selTime) : ds + ' \u00b7 choose a time';
                },

                sync() { this.value = this.selDate && this.selTime ? this.selDate + 'T' + this.selTime : ''; },
                pickDay(c) {
                    if (c.disabled) return;
                    this.selDate = c.k;
                    if (this.selTime && this.slotDisabled(this.selTime)) this.selTime = null;
                    this.sync();
                },
                pickTime(t) {
                    if (this.slotDisabled(t)) return;
                    this.selTime = t;
                    this.sync();
                },
                usePreset(p) {
                    if (this.dayDisabled(p.k) || this.slotDisabled(p.t, p.k)) return;
                    this.selDate = p.k; this.selTime = p.t;
                    const [y, m] = this.parts(p.k);
                    this.view = { y, m: m - 1 };
                    this.sync();
                },
                clear() { this.selDate = null; this.selTime = null; this.value = ''; },
            }));
        });
    </script>
@endonce

<div x-data="dateTimePicker({ value: @js($value), now: @js($now), maxDays: {{ (int) $maxDays }}, lead: {{ (int) $lead }} })"
     @keydown.escape.window="open = false" class="relative">

    <input type="hidden" name="{{ $name }}" :value="value" x-bind:disabled="typeof when !== 'undefined' && when !== 'later'">

    <button type="button" @click="open = !open" :aria-expanded="open" aria-haspopup="dialog"
            class="flex w-full items-center justify-between gap-3 rounded-lg border bg-white px-3.5 py-2.5 text-left text-sm shadow-sm transition hover:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-500 {{ $invalid ? 'border-red-400' : 'border-slate-300' }}"
            :class="open ? 'border-brand-500 ring-2 ring-brand-500/30' : ''">
        <span class="flex min-w-0 items-center gap-2.5">
            <svg class="h-5 w-5 shrink-0 text-brand-600" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="16" rx="2"/><path stroke-linecap="round" d="M3 10h18M8 3v4M16 3v4"/></svg>
            <span class="truncate" :class="selDate ? 'font-medium text-slate-900' : 'text-slate-400'" x-text="display() || 'Select date and time'">Select date and time</span>
        </span>
        <svg class="h-4 w-4 shrink-0 text-slate-400 transition" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>
    </button>

    <div x-show="open" x-cloak x-transition.origin.top.left.duration.150ms @click.outside="open = false" role="dialog" aria-label="Choose date and time"
         class="absolute left-0 z-30 mt-2 w-[min(calc(100vw-3rem),36rem)] overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl">

        {{-- Quick picks --}}
        <div class="flex flex-wrap items-center gap-2 border-b border-slate-100 bg-slate-50 px-4 py-3">
            <span class="text-xs font-semibold uppercase tracking-wide text-slate-400">Quick pick</span>
            <template x-for="p in presets" :key="p.label">
                <button type="button" @click="usePreset(p)" x-text="p.label"
                        :disabled="dayDisabled(p.k) || slotDisabled(p.t, p.k)"
                        class="rounded-full bg-white px-3 py-1 text-xs font-medium text-slate-700 ring-1 ring-slate-200 transition hover:bg-brand-50 hover:text-brand-700 hover:ring-brand-300 disabled:cursor-not-allowed disabled:opacity-40"></button>
            </template>
        </div>

        <div class="grid sm:grid-cols-[17.5rem_1fr]">
            {{-- Calendar --}}
            <div class="p-4 sm:border-r sm:border-slate-100">
                <div class="mb-3 flex items-center justify-between">
                    <button type="button" @click="shift(-1)" :disabled="!canPrev()" aria-label="Previous month"
                            class="flex h-8 w-8 items-center justify-center rounded-lg text-slate-600 hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-30">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m15 6-6 6 6 6"/></svg>
                    </button>
                    <p class="text-sm font-semibold text-slate-800" x-text="monthLabel()"></p>
                    <button type="button" @click="shift(1)" :disabled="!canNext()" aria-label="Next month"
                            class="flex h-8 w-8 items-center justify-center rounded-lg text-slate-600 hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-30">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m9 6 6 6-6 6"/></svg>
                    </button>
                </div>
                <div class="mb-1 grid grid-cols-7 text-center text-[11px] font-semibold uppercase text-slate-400">
                    <template x-for="w in weekdays" :key="w"><span class="py-1" x-text="w"></span></template>
                </div>
                <div class="grid grid-cols-7 gap-y-1 text-center">
                    <template x-for="(c, i) in cells()" :key="i">
                        <div class="flex h-9 items-center justify-center">
                            <button type="button" x-show="!c.blank" @click="pickDay(c)" :disabled="c.disabled" x-text="c.d"
                                    :class="c.selected ? 'bg-brand-800 font-semibold text-white shadow' : (c.disabled ? 'cursor-not-allowed text-slate-300' : 'text-slate-700 hover:bg-brand-50 hover:text-brand-700') + (c.today && !c.selected ? ' font-semibold text-brand-700 ring-1 ring-brand-300' : '')"
                                    class="h-9 w-9 rounded-full text-sm transition"></button>
                        </div>
                    </template>
                </div>
            </div>

            {{-- Time slots --}}
            <div class="border-t border-slate-100 p-4 sm:border-t-0">
                <p class="mb-3 text-sm font-semibold text-slate-800">Time</p>
                <p x-show="!selDate" class="rounded-lg bg-slate-50 px-3 py-6 text-center text-sm text-slate-400">Pick a date first</p>
                <div x-show="selDate" class="grid max-h-64 grid-cols-3 gap-1.5 overflow-y-auto pr-1">
                    <template x-for="t in slots" :key="t">
                        <button type="button" @click="pickTime(t)" :disabled="slotDisabled(t)" x-text="fmtTime(t)"
                                :class="selTime === t ? 'bg-brand-800 font-semibold text-white shadow' : (slotDisabled(t) ? 'cursor-not-allowed text-slate-300' : 'bg-white text-slate-700 ring-1 ring-slate-200 hover:bg-brand-50 hover:text-brand-700 hover:ring-brand-300')"
                                class="rounded-lg px-1 py-2 text-xs transition sm:text-sm"></button>
                    </template>
                </div>
            </div>
        </div>

        {{-- Footer --}}
        <div class="flex items-center justify-between gap-3 border-t border-slate-100 bg-slate-50 px-4 py-3">
            <button type="button" @click="clear()" class="text-sm font-medium text-slate-500 hover:text-slate-700">Clear</button>
            <p class="hidden min-w-0 truncate text-xs text-slate-500 sm:block" x-text="display() || 'No date selected'"></p>
            <button type="button" @click="open = false" class="rounded-lg bg-brand-800 px-4 py-1.5 text-sm font-semibold text-white hover:bg-brand-700">Done</button>
        </div>
    </div>
</div>
