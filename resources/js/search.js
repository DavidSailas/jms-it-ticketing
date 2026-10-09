/**
 * Ctrl+K command palette: jump to a page instantly, or search tickets, people and companies.
 *
 * - Pages and shortcuts are filtered in the browser (no waiting).
 * - Tickets / people / companies come from /search, debounced, with stale answers thrown away.
 * - Results are real links, so middle click and "open in new tab" work. Keyboard: arrows, Enter, Esc, Home, End.
 * - The last few things you opened are remembered in this browser only (localStorage).
 */
const RECENT_KEY = 'jms.search.recent';
const RECENT_MAX = 5;

const ICONS = {
    ticket: '<path d="M4 8a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v2a2 2 0 0 0 0 4v2a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-2a2 2 0 0 0 0-4V8z"/><path d="M14 6v12" stroke-dasharray="2 2"/>',
    person: '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
    company: '<path d="M4 21V5a1 1 0 0 1 1-1h8a1 1 0 0 1 1 1v16M14 9h5a1 1 0 0 1 1 1v11M2 21h20M8 8h2M8 12h2M8 16h2"/>',
    search: '<circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/>',
    page: '<path d="M5 12h14M13 6l6 6-6 6"/>',
};

function readRecent() {
    try {
        const list = JSON.parse(localStorage.getItem(RECENT_KEY) || '[]');

        return Array.isArray(list) ? list.filter((r) => r && typeof r.url === 'string' && r.url.startsWith('/')).slice(0, RECENT_MAX) : [];
    } catch (e) {
        return [];
    }
}

function writeRecent(list) {
    try {
        localStorage.setItem(RECENT_KEY, JSON.stringify(list));
    } catch (e) { /* private mode or storage full: recents are a nicety, never an error */ }
}

document.addEventListener('alpine:init', () => {
    window.Alpine.data('commandPalette', (cfg) => {
        let timer; let controller; let seq = 0; let opener = null;

        return {
            open: false,
            q: '',
            loading: false,
            failed: false,
            active: 0,
            server: { tickets: [], people: [], companies: [] },
            recent: [],
            mac: /Mac|iPhone|iPad/.test(navigator.platform || ''),

            init() {
                this.recent = readRecent();
                window.addEventListener('keydown', (e) => this.onGlobalKey(e));
                // Anything that wants to open the palette (e.g. the header button) can also dispatch this.
                window.addEventListener('open-search', () => this.show());
            },

            get shortcut() { return this.mac ? 'Cmd K' : 'Ctrl K'; },
            get term() { return this.q.trim(); },
            get searching() { return this.term.length >= 2; },

            onGlobalKey(e) {
                const key = e.key?.toLowerCase();
                if ((e.ctrlKey || e.metaKey) && key === 'k') {
                    e.preventDefault();
                    this.open ? this.hide() : this.show();

                    return;
                }
                // "/" opens it too, unless the person is typing somewhere.
                if (key === '/' && !this.open && !e.ctrlKey && !e.metaKey && !e.altKey && !this.typing(e.target)) {
                    e.preventDefault();
                    this.show();
                }
            },

            typing(el) {
                return !!el && (['INPUT', 'TEXTAREA', 'SELECT'].includes(el.tagName) || el.isContentEditable);
            },

            show() {
                if (this.open) return;
                opener = document.activeElement;
                // Always start clean: recent items and shortcuts, not the last search.
                this.recent = readRecent();
                this.q = '';
                this.server = { tickets: [], people: [], companies: [] };
                this.failed = false;
                this.open = true;
                this.active = 0;
                document.documentElement.classList.add('overflow-hidden');
                this.$nextTick(() => { this.$refs.input?.focus(); this.$refs.input?.select(); });
            },

            hide() {
                if (!this.open) return;
                this.open = false;
                clearTimeout(timer);
                controller?.abort();
                this.loading = false;
                document.documentElement.classList.remove('overflow-hidden');
                this.$nextTick(() => opener?.focus?.());
            },

            /** Called on every keystroke in the box. */
            typed() {
                this.active = 0;
                this.failed = false;
                clearTimeout(timer);
                controller?.abort();

                if (!this.searching) {
                    this.server = { tickets: [], people: [], companies: [] };
                    this.loading = false;

                    return;
                }
                this.loading = true;
                timer = setTimeout(() => this.fetchResults(), 180);
            },

            async fetchResults() {
                const mine = ++seq;
                controller = new AbortController();
                try {
                    const res = await fetch(`${cfg.url}?q=${encodeURIComponent(this.term)}`, {
                        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        credentials: 'same-origin',
                        signal: controller.signal,
                    });
                    if (!res.ok) throw new Error(res.status);
                    const json = await res.json();
                    if (mine !== seq) return; // a newer search already took over
                    this.server = { tickets: json.tickets || [], people: json.people || [], companies: json.companies || [] };
                    this.failed = false;
                } catch (e) {
                    if (e.name === 'AbortError') return;
                    if (mine === seq) this.failed = true;
                } finally {
                    if (mine === seq) this.loading = false;
                }
            },

            /** The groups on screen, in order. Each item knows its position so the keyboard and mouse agree. */
            get sections() {
                const out = [];
                const add = (key, label, items) => { if (items.length) out.push({ key, label, items }); };
                const needle = this.term.toLowerCase();

                if (!this.searching) {
                    add('recent', 'Recent', this.recent.map((r) => ({ ...r, kind: r.kind || 'page' })));
                    add('pages', 'Go to', cfg.pages.filter((p) => !needle || p.title.toLowerCase().includes(needle)).map((p) => ({ ...p, kind: 'page' })));
                } else {
                    add('tickets', 'Tickets', this.server.tickets.map((t) => ({ ...t, kind: 'ticket' })));
                    add('people', 'People', this.server.people.map((p) => ({ ...p, kind: 'person' })));
                    add('companies', 'Companies', this.server.companies.map((c) => ({ ...c, kind: 'company' })));
                    add('pages', 'Go to', cfg.pages.filter((p) => p.title.toLowerCase().includes(needle)).slice(0, 4).map((p) => ({ ...p, kind: 'page' })));
                    add('more', 'Keep looking', cfg.more.map((m) => ({
                        title: m.title.replace('{q}', this.term), url: m.url + encodeURIComponent(this.term), kind: 'more',
                    })));
                }

                let i = 0;
                out.forEach((s) => s.items.forEach((item) => { item.idx = i++; item.dom = `palette-opt-${item.idx}`; }));

                return out;
            },

            get flat() { return this.sections.flatMap((s) => s.items); },
            get nothing() {
                return this.searching && !this.loading && !this.failed
                    && !this.server.tickets.length && !this.server.people.length && !this.server.companies.length;
            },
            get status() {
                if (this.failed) return 'Search is not available right now.';
                if (this.loading) return 'Searching...';
                const n = this.flat.length;

                return n ? `${n} result${n === 1 ? '' : 's'}` : 'No results';
            },

            icon(kind) { return ICONS[kind] || ICONS.page; },

            /** Splits text around the searched words so matches can be shown in bold without any HTML injection. */
            marks(text) {
                const s = String(text ?? '');
                const n = this.term.toLowerCase();
                if (!this.searching || !s) return [{ t: s, hit: false }];
                const at = s.toLowerCase().indexOf(n);
                if (at < 0) return [{ t: s, hit: false }];

                return [
                    { t: s.slice(0, at), hit: false },
                    { t: s.slice(at, at + n.length), hit: true },
                    { t: s.slice(at + n.length), hit: false },
                ].filter((p) => p.t);
            },

            onKey(e) {
                const n = this.flat.length;
                if (e.key === 'ArrowDown') { e.preventDefault(); this.move(n ? (this.active + 1) % n : 0); }
                else if (e.key === 'ArrowUp') { e.preventDefault(); this.move(n ? (this.active - 1 + n) % n : 0); }
                else if (e.key === 'Home' && n) { e.preventDefault(); this.move(0); }
                else if (e.key === 'End' && n) { e.preventDefault(); this.move(n - 1); }
                else if (e.key === 'Enter') {
                    const item = this.flat[this.active];
                    if (item) { e.preventDefault(); this.choose(item); window.location.href = item.url; }
                } else if (e.key === 'Escape') { e.preventDefault(); this.hide(); }
                else if (e.key === 'Tab') { e.preventDefault(); this.$refs.input.focus(); } // keep focus inside the dialog
            },

            move(i) {
                this.active = i;
                this.$nextTick(() => document.getElementById(`palette-opt-${i}`)?.scrollIntoView({ block: 'nearest' }));
            },

            /** Remember tickets, people and companies you opened (not plain pages or "keep looking" links). */
            choose(item) {
                if (!['ticket', 'person', 'company'].includes(item.kind)) return;
                const keep = { kind: item.kind, title: item.no ? `${item.no}  ${item.title}` : item.title, sub: item.sub || '', url: item.url };
                writeRecent([keep, ...readRecent().filter((r) => r.url !== keep.url)].slice(0, RECENT_MAX));
            },

            clearRecent() {
                writeRecent([]);
                this.recent = [];
                this.active = 0;
            },
        };
    });
});
