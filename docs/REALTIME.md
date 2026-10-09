# Live updates (Laravel Reverb)

With live updates ON, these refresh the moment something happens, instead of on the next poll:
the notification bell and its pop-ups, the "waiting for acceptance" queue, the dashboard KPIs and charts,
the Kanban board, and a "this ticket was just updated" banner on an open ticket.

With live updates OFF (the default), everything keeps working exactly as before through polling.
Polling also stays on as a safety net when live updates are ON, so a dropped connection loses nothing.

Nothing private travels over the websocket: only "ticket 42 changed". Each browser then re-fetches what it is
allowed to see through the normal pages. Channels are private and checked in `routes/channels.php`.

## Set up (once)

1. `composer require laravel/reverb`
2. `npm install laravel-echo pusher-js` then `npm run build`
3. In `.env`:
   ```
   BROADCAST_CONNECTION=reverb
   REVERB_APP_ID=jms-desk
   REVERB_APP_KEY=<any long random string>
   REVERB_APP_SECRET=<another long random string>
   REVERB_HOST=localhost
   REVERB_PORT=8080
   REVERB_SCHEME=http
   REVERB_SERVER_HOST=0.0.0.0
   REVERB_SERVER_PORT=8080
   ```
   (`php -r "echo bin2hex(random_bytes(20));"` makes a good key or secret.)
4. `php artisan config:clear`
5. Start the websocket server and leave it running: `php artisan reverb:start`
6. Open the Dashboard in two browsers (two different people). Resolve or comment on a ticket in one;
   the other updates within a second. The Board page shows a green "Live" dot when connected.

## Going live on a server

- Use HTTPS. Set `REVERB_SCHEME=https`, `REVERB_PORT=443`, and put Reverb behind your web server
  (proxy `/app` and `/apps` to port 8080 with websocket upgrade headers). If browsers reach Reverb
  at a different address than the server does, set `REVERB_PUBLIC_HOST`, `REVERB_PUBLIC_PORT`, `REVERB_PUBLIC_SCHEME`.
- Keep `php artisan reverb:start` running with Supervisor (or a systemd service) and restart it on every deploy
  (`php artisan reverb:restart`).
- Raise the open-files limit for the Reverb process if you expect more than about 1,000 people online at once.

## Turning it off

Set `BROADCAST_CONNECTION=log` and run `php artisan config:clear`. No code change is needed.
