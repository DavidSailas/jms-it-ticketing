/**
 * Live updates (Laravel Reverb). Only starts when the server put window.JMS_REALTIME on the page, which it does
 * only when Reverb is switched on. Everything else keeps working from polling, so a dropped connection costs nothing.
 *
 * It does not carry ticket data. It fires small browser events, and each part of the page re-fetches what it needs:
 *   live-ticket        a ticket this person can see changed          (detail: { ticket_id, actor_id, kind, status, ... })
 *   live-notification  a new bell notification is waiting
 *   live-status        the connection went up or down                (detail: { connected })
 */
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

const cfg = window.JMS_REALTIME;

if (cfg && cfg.key && cfg.host) {
    window.Pusher = Pusher;

    const echo = new Echo({
        broadcaster: 'reverb',
        key: cfg.key,
        wsHost: cfg.host,
        wsPort: cfg.port,
        wssPort: cfg.port,
        forceTLS: cfg.scheme === 'https',
        enabledTransports: ['ws', 'wss'],
    });
    window.Echo = echo;

    // The same change can arrive on several channels (e.g. as the owner and as a company admin): act on it once.
    const seen = new Set();
    const fire = (name, detail) => {
        if (detail && detail.uid) {
            if (seen.has(detail.uid)) return;
            seen.add(detail.uid);
            setTimeout(() => seen.delete(detail.uid), 10000);
        }
        window.dispatchEvent(new CustomEvent(name, { detail }));
    };

    cfg.channels.forEach((name) => {
        echo.private(name)
            .listen('.ticket.changed', (e) => fire('live-ticket', e))
            .listen('.notification.pushed', (e) => fire('live-notification', e));
    });

    const connection = echo.connector.pusher.connection;
    const status = (connected) => {
        window.JMS_LIVE = connected;
        fire('live-status', { connected });
    };
    connection.bind('connected', () => status(true));
    connection.bind('disconnected', () => status(false));
    connection.bind('unavailable', () => status(false));
    connection.bind('failed', () => status(false));
}
