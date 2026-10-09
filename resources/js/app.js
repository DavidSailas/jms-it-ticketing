import './bootstrap';
import './realtime';
import './charts';
import './search';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

/**
 * Notification bell: dropdown + live toast pop-ups.
 * Data comes from the database (see NotificationController@feed).
 */
Alpine.data('notificationBell', (cfg) => ({
    open: false,
    unread: cfg.initial.unread,
    items: cfg.initial.items,
    toasts: [],
    lastTs: Math.max(0, ...cfg.initial.items.map((i) => i.ts)),
    kinds: cfg.kinds,

    init() {
        this.timer = setInterval(() => this.poll(), cfg.pollMs);
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) this.poll();
        });
    },

    async poll() {
        if (document.hidden) return;

        try {
            const res = await fetch(cfg.feedUrl, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            if (!res.ok) return;

            const data = await res.json();
            const fresh = data.items.filter((i) => !i.read && i.ts > this.lastTs);

            this.items = data.items;
            this.unread = data.unread;

            if (fresh.length) {
                this.lastTs = Math.max(this.lastTs, ...fresh.map((i) => i.ts));
                fresh.slice(0, 3).forEach((i) => this.pushToast(i));
            }
        } catch (e) {
            /* offline or session expired: try again on the next tick */
        }
    },

    pushToast(item) {
        this.toasts.push(item);
        setTimeout(() => this.dismiss(item.id), 8000);
    },

    dismiss(id) {
        this.toasts = this.toasts.filter((t) => t.id !== id);
    },

    async markAll() {
        this.items = this.items.map((i) => ({ ...i, read: true }));
        this.unread = 0;

        await fetch(cfg.readAllUrl, {
            method: 'POST',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': cfg.csrf, 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        }).catch(() => {});
    },

    look(kind) {
        return this.kinds[kind] || this.kinds.default;
    },

    badge() {
        return this.unread > 9 ? '9+' : String(this.unread);
    },
}));

/**
 * Live "Waiting for acceptance" queue (admins / super admins only).
 *
 * One store feeds every place that shows the queue (dashboard panel, sidebar badge,
 * KPI card, tab title, ticket list banner), so they always agree. It polls a small
 * JSON endpoint, pauses while the tab is hidden and catches up the moment it is visible.
 */
Alpine.store('pending', {
    count: 0,
    items: [],
    fresh: [],          // ids of tickets that just arrived (highlighted for a few seconds)
    newSinceLoad: 0,    // tickets that arrived since this page was opened
    latestId: 0,
    online: true,
    updatedAt: Date.now(),
    now: Date.now(),
    started: false,
    busy: false,
    url: '',
    pollMs: 8000,
    baseTitle: '',

    start(cfg) {
        if (this.started) return;
        this.started = true;
        this.url = cfg.url;
        this.pollMs = cfg.pollMs || 8000;
        this.baseTitle = document.title;

        this.apply(cfg.initial, false);

        setInterval(() => this.poll(), this.pollMs);
        setInterval(() => { this.now = Date.now(); }, 5000);
        document.addEventListener('visibilitychange', () => { if (!document.hidden) this.poll(); });
        window.addEventListener('live-ticket', () => this.poll()); // websocket nudge: fetch right now
        window.addEventListener('online', () => this.poll());
        window.addEventListener('offline', () => { this.online = false; });
    },

    async poll() {
        if (this.busy || document.hidden) return;
        this.busy = true;

        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 10000);

        try {
            const res = await fetch(`${this.url}?after=${this.latestId}`, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                cache: 'no-store',
                signal: controller.signal,
            });

            if (!res.ok) throw new Error(`HTTP ${res.status}`);

            this.apply(await res.json(), true);
            this.online = true;
        } catch (e) {
            this.online = false; // shown as "Reconnecting..."; the next tick retries
        } finally {
            clearTimeout(timeout);
            this.busy = false;
        }
    },

    apply(data, announce) {
        if (announce && data.new_count > 0) {
            this.newSinceLoad += data.new_count;
            data.new.forEach((t) => this.markFresh(t.id));
            // Let the notification bell fetch right away so its toast appears with the ticket.
            window.dispatchEvent(new CustomEvent('pending-new', { detail: { count: data.new_count, tickets: data.new } }));
        }

        this.latestId = Math.max(this.latestId, data.latest_id || 0);
        this.count = data.count;
        this.items = data.items;
        this.updatedAt = this.now = Date.now();
        this.syncTitle();
    },

    markFresh(id) {
        this.fresh.push(id);
        setTimeout(() => { this.fresh = this.fresh.filter((x) => x !== id); }, 15000);
    },

    isFresh(id) {
        return this.fresh.includes(id);
    },

    syncTitle() {
        document.title = this.count > 0 ? `(${this.count}) ${this.baseTitle}` : this.baseTitle;
    },

    get status() {
        return this.online ? 'Live' : 'Reconnecting…';
    },

    get updatedLabel() {
        const s = Math.max(0, Math.round((this.now - this.updatedAt) / 1000));
        if (s < 10) return 'just now';
        return s < 60 ? `${s}s ago` : `${Math.floor(s / 60)}m ago`;
    },
});

Alpine.start();
