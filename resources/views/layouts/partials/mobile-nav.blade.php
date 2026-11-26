{{-- Phone navigation (< 768px): a bottom tab bar with a "More" sheet. Laptops and tablets keep the sidebar.
     Uses the variables prepared in layouts/app.blade.php ($nav, $navAllowed, $icons, $isUser, $pendingFeed, $todayBookings, $theme). --}}
@php
    // [route, short label, icon, active route patterns]
    $tabDefs = $isUser
        ? [['dashboard', 'Request', 'plus', 'dashboard|tickets.create'], ['tickets.index', 'Tickets', 'list', 'tickets.index|tickets.show|tickets.edit'], ['calendar.index', 'Calendar', 'calendar', 'calendar.*']]
        : [['dashboard', 'Home', 'grid', 'dashboard'], ['tickets.index', 'Tickets', 'list', 'tickets.*'], ['schedule.index', 'Schedule', 'calendar', 'schedule.*']];

    // Only offer a tab when this person is allowed to open that page.
    $navByRoute = collect($nav)->keyBy(1);
    $mnTabs     = collect($tabDefs)->filter(fn ($t) => isset($navByRoute[$t[0]]) && $navAllowed($navByRoute[$t[0]][3]))->values();
    $tabRoutes  = $mnTabs->pluck(0)->all();
    $moreItems  = collect($nav)->filter(fn ($n) => $navAllowed($n[3]) && ! in_array($n[1], $tabRoutes))->values();
    $moreActive = $moreItems->contains(fn ($n) => request()->routeIs(explode('|', $n[2]))) || request()->routeIs('profile.*');

    // Small counters shown on a tab or tile: the live "waiting for acceptance" queue and today's bookings.
    $mnBadge = function (string $route) use ($pendingFeed, $todayBookings, $availableCount) {
        if ($route === 'tickets.index' && $pendingFeed) return ['store', null];
        if ($route === 'schedule.index' && $todayBookings > 0) return ['num', $todayBookings > 99 ? '99+' : $todayBookings];
        if ($route === 'tickets.available' && $availableCount > 0) return ['num', $availableCount > 99 ? '99+' : $availableCount];
        return null;
    };
@endphp

<div x-data="{ more: false, typing: false, unread: 0 }"
     @unread-changed.window="unread = $event.detail"
     @focusin.window="typing = $event.target.matches('input:not([type=checkbox]):not([type=radio]):not([type=button]):not([type=submit]):not([type=file]), textarea, select')"
     @focusout.window="typing = false"
     @keydown.escape.window="more = false"
     x-effect="document.body.classList.toggle('mn-typing', typing); document.body.classList.toggle('sb-lock', more)">

    <nav class="mn" aria-label="Primary">
        @foreach ($mnTabs as [$route, $label, $icon, $pattern])
            @php($active = request()->routeIs(explode('|', $pattern)))
            @php($badge = $mnBadge($route))
            <a href="{{ route($route) }}" class="mn-tab {{ $active ? 'is-active' : '' }}" @if ($active) aria-current="page" @endif>
                <span class="mn-ico">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="{{ $active ? 2 : 1.7 }}" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $icons[$icon] !!}</svg>
                    @if ($badge && $badge[0] === 'store')
                        <span x-data x-show="$store.pending.count > 0" x-cloak x-text="$store.pending.count > 99 ? '99+' : $store.pending.count" class="mn-badge is-violet" aria-label="Tickets waiting for acceptance"></span>
                    @elseif ($badge)
                        <span class="mn-badge" aria-label="{{ $badge[1] }} booked for today">{{ $badge[1] }}</span>
                    @endif
                </span>
                <span class="mn-lbl">{{ $label }}</span>
            </a>
        @endforeach

        <a href="{{ route('notifications.index') }}" class="mn-tab {{ request()->routeIs('notifications.*') ? 'is-active' : '' }}" @if (request()->routeIs('notifications.*')) aria-current="page" @endif>
            <span class="mn-ico">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0"/></svg>
                <span x-show="unread > 0" x-cloak x-text="unread > 9 ? '9+' : unread" class="mn-badge" :aria-label="unread + ' unread notifications'"></span>
            </span>
            <span class="mn-lbl">Alerts</span>
        </a>

        <button type="button" class="mn-tab {{ $moreActive ? 'is-active' : '' }}" @click="more = true" :aria-expanded="more.toString()" aria-haspopup="dialog">
            <span class="mn-ico">
                <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="5.5" cy="12" r="1.7"/><circle cx="12" cy="12" r="1.7"/><circle cx="18.5" cy="12" r="1.7"/></svg>
            </span>
            <span class="mn-lbl">More</span>
        </button>
    </nav>

    {{-- "More" sheet --}}
    <div x-show="more" x-cloak class="mnm-root"
         x-effect="if (more) $nextTick(() => { const b = $el.querySelector('.mnm-x'); b && b.focus({ preventScroll: true }) })">
        <div class="mnm-backdrop" @click="more = false" aria-hidden="true"></div>
        <div class="mnm-sheet" role="dialog" aria-modal="true" aria-label="More">
            <span class="mnm-grab" aria-hidden="true"></span>

            <div class="mnm-head">
                <a href="{{ route('profile.edit') }}" class="mnm-me">
                    <x-avatar :user="auth()->user()" :zoom="false" size="h-11 w-11" text="text-sm" />
                    <span class="mnm-me-text">
                        <span class="mnm-me-name">{{ auth()->user()->name }}</span>
                        <span class="mnm-me-sub">{{ auth()->user()->company ?: auth()->user()->roleLabel() }}</span>
                    </span>
                </a>
                <button type="button" class="mnm-x" @click="more = false" aria-label="Close">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
                </button>
            </div>

            <div class="mnm-body">
                <div class="mnm-grid">
                    @foreach ($moreItems as [$label, $route, $pattern, $roles, $icon, $grp])
                        @php($active = request()->routeIs(explode('|', $pattern)))
                        @php($badge = $mnBadge($route))
                        <a href="{{ route($route) }}" class="mnm-tile {{ $active ? 'is-active' : '' }}" @if ($active) aria-current="page" @endif>
                            <span class="mnm-tile-ico">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $icons[$icon] !!}</svg>
                                @if ($badge && $badge[0] === 'num') <span class="mn-badge">{{ $badge[1] }}</span> @endif
                            </span>
                            <span class="mnm-tile-lbl">{{ $label }}</span>
                        </a>
                    @endforeach
                    <a href="{{ route('profile.edit') }}" class="mnm-tile {{ request()->routeIs('profile.*') ? 'is-active' : '' }}">
                        <span class="mnm-tile-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $icons['profile'] !!}</svg></span>
                        <span class="mnm-tile-lbl">My profile</span>
                    </a>
                </div>
            </div>

            <form method="POST" action="{{ route('logout') }}" class="mnm-foot">
                @csrf
                <button type="submit" class="mnm-logout">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 12H3.5M7 8l-4 4 4 4M10 5V4.5A1.5 1.5 0 0 1 11.5 3h7A1.5 1.5 0 0 1 20 4.5v15a1.5 1.5 0 0 1-1.5 1.5h-7A1.5 1.5 0 0 1 10 19.5V19"/></svg>
                    Log out
                </button>
            </form>
        </div>
    </div>
</div>

<style>
    .mn, .mnm-root, .mn-fab { display: none; }

    @media (max-width: 767px) {
        /* The slide-out sidebar and the desktop-only header buttons give way to the bottom bar. */
        aside.sb, .sb-handle, .mn-hide-phone { display: none !important; }
        .sb-main { padding-bottom: calc(4.4rem + env(safe-area-inset-bottom, 0px)); }
        body.mn-typing .mn, body.mn-typing .mn-fab { display: none; }

        .mn { display: flex; position: fixed; left: 0; right: 0; bottom: 0; z-index: 30; background: rgba(255,255,255,.96); -webkit-backdrop-filter: blur(12px); backdrop-filter: blur(12px); border-top: 1px solid #e2e8f0; box-shadow: 0 -8px 24px -16px rgba(15,23,42,.35); padding: .3rem max(.25rem, env(safe-area-inset-right)) calc(.3rem + env(safe-area-inset-bottom, 0px)) max(.25rem, env(safe-area-inset-left)); }
        .mn-tab { flex: 1 1 0; min-width: 0; display: flex; flex-direction: column; align-items: center; gap: .2rem; padding: .15rem 0; border: 0; background: transparent; color: #64748b; font-size: .6875rem; font-weight: 600; line-height: 1.1; text-decoration: none; cursor: pointer; -webkit-tap-highlight-color: transparent; }
        .mn-tab:focus-visible { outline: 2px solid #2563eb; outline-offset: -2px; border-radius: .75rem; }
        .mn-ico { position: relative; display: grid; place-items: center; width: 3.4rem; height: 1.85rem; border-radius: 9999px; transition: background-color .2s ease, transform .15s ease; }
        .mn-ico svg { width: 1.45rem; height: 1.45rem; }
        .mn-tab:active .mn-ico { transform: scale(.92); }
        .mn-tab.is-active { color: rgb(var(--brand-700)); }
        .mn-tab.is-active .mn-ico { background: rgb(var(--brand-100)); }
        .mn-lbl { max-width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .mn-badge { position: absolute; top: -.2rem; right: .3rem; min-width: 1.1rem; height: 1.1rem; padding: 0 .3rem; border-radius: 9999px; background: #ef4444; color: #fff; font-size: .625rem; font-weight: 700; line-height: 1.1rem; text-align: center; box-shadow: 0 0 0 2px #fff; }
        .mn-badge.is-violet { background: #7c3aed; }

        /* Floating primary action (used by the tickets list) */
        .mn-fab { position: fixed; right: 1rem; bottom: calc(4.4rem + env(safe-area-inset-bottom, 0px) + .9rem); z-index: 29; display: inline-flex; align-items: center; gap: .45rem; height: 3.25rem; padding: 0 1.1rem 0 .95rem; border-radius: 9999px; background: rgb(var(--brand-600)); color: #fff; font-size: .875rem; font-weight: 600; text-decoration: none; box-shadow: 0 10px 24px -8px rgba(15,23,42,.55); -webkit-tap-highlight-color: transparent; }
        .mn-fab:active { transform: scale(.96); }
        .mn-fab svg { width: 1.2rem; height: 1.2rem; }

        /* More sheet */
        .mnm-root { display: block; position: fixed; inset: 0; z-index: 45; }
        .mnm-root[style*="display: none"] { display: none; }
        .mnm-backdrop { position: absolute; inset: 0; background: rgba(15,23,42,.5); }
        .mnm-sheet { position: absolute; left: 0; right: 0; bottom: 0; display: flex; flex-direction: column; max-height: 88vh; max-height: 88dvh; background: #fff; border-radius: 1.25rem 1.25rem 0 0; box-shadow: 0 -20px 50px -20px rgba(15,23,42,.5); animation: mnm-up .22s ease-out; }
        .mnm-grab { display: block; width: 2.5rem; height: .3rem; margin: .55rem auto 0; border-radius: 9999px; background: #cbd5e1; }
        .mnm-head { display: flex; align-items: center; justify-content: space-between; gap: .75rem; padding: .7rem 1rem .5rem; }
        .mnm-me { display: flex; align-items: center; gap: .75rem; min-width: 0; text-decoration: none; color: inherit; }
        .mnm-me-text { min-width: 0; }
        .mnm-me-name, .mnm-me-sub { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .mnm-me-name { font-size: .95rem; font-weight: 700; color: #0f172a; }
        .mnm-me-sub { font-size: .75rem; color: #64748b; }
        .mnm-x { display: inline-grid; place-items: center; flex: none; width: 2.5rem; height: 2.5rem; border: 0; border-radius: 9999px; background: #f1f5f9; color: #475569; cursor: pointer; }
        .mnm-body { flex: 1 1 auto; min-height: 0; overflow-y: auto; overscroll-behavior: contain; padding: .5rem 1rem 1rem; }
        .mnm-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: .6rem; }
        .mnm-tile { display: flex; flex-direction: column; align-items: center; gap: .45rem; padding: .85rem .35rem .7rem; border-radius: 1rem; background: #f8fafc; border: 1px solid #e2e8f0; color: #334155; text-decoration: none; text-align: center; -webkit-tap-highlight-color: transparent; }
        .mnm-tile:active { transform: scale(.96); }
        .mnm-tile.is-active { background: rgb(var(--brand-50)); border-color: rgb(var(--brand-200)); color: rgb(var(--brand-700)); }
        .mnm-tile-ico { position: relative; display: grid; place-items: center; width: 2.75rem; height: 2.75rem; border-radius: .85rem; background: #fff; box-shadow: 0 1px 2px rgba(15,23,42,.08); }
        .mnm-tile-ico svg { width: 1.4rem; height: 1.4rem; }
        .mnm-tile-lbl { font-size: .75rem; font-weight: 600; line-height: 1.2; }
        .mnm-foot { flex: none; padding: .75rem 1rem calc(.75rem + env(safe-area-inset-bottom, 0px)); border-top: 1px solid #e2e8f0; background: #f8fafc; }
        .mnm-logout { display: flex; align-items: center; justify-content: center; gap: .5rem; width: 100%; min-height: 2.9rem; border: 0; border-radius: .8rem; background: #fff; color: #dc2626; font-size: .9rem; font-weight: 600; box-shadow: inset 0 0 0 1px #fecaca; cursor: pointer; }
        .mnm-logout:active { background: #fef2f2; }

        /* Sort & rows sheet (tickets list) */
        .tkf-root { position: fixed; inset: 0; z-index: 45; }
        .tkf-backdrop { position: absolute; inset: 0; background: rgba(15,23,42,.5); }
        .tkf-sheet { position: absolute; left: 0; right: 0; bottom: 0; display: flex; flex-direction: column; max-height: 88vh; max-height: 88dvh; background: #fff; border-radius: 1.25rem 1.25rem 0 0; box-shadow: 0 -20px 50px -20px rgba(15,23,42,.5); animation: mnm-up .22s ease-out; }
        .tkf-grab { display: block; width: 2.5rem; height: .3rem; margin: .55rem auto 0; border-radius: 9999px; background: #cbd5e1; }
        .tkf-head { display: flex; align-items: center; justify-content: space-between; padding: .7rem 1rem .25rem; }
        .tkf-x { display: inline-grid; place-items: center; width: 2.5rem; height: 2.5rem; border: 0; border-radius: 9999px; background: #f1f5f9; color: #475569; cursor: pointer; }
        .tkf-body { flex: 1 1 auto; min-height: 0; overflow-y: auto; overscroll-behavior: contain; padding: 0 1rem 1rem; }
        .tkf-label { margin: .9rem 0 .4rem; font-size: .68rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: #64748b; }
        .tkf-list { overflow: hidden; border: 1px solid #e2e8f0; border-radius: .9rem; }
        .tkf-opt { display: flex; align-items: center; justify-content: space-between; gap: .75rem; min-height: 3rem; padding: 0 1rem; font-size: .9rem; color: #334155; cursor: pointer; }
        .tkf-opt + .tkf-opt { border-top: 1px solid #f1f5f9; }
        .tkf-opt.is-on { background: rgb(var(--brand-50)); color: rgb(var(--brand-700)); font-weight: 600; }
        .tkf-opt:focus-within { outline: 2px solid #2563eb; outline-offset: -2px; }
        .tkf-seg { display: flex; padding: .2rem; border-radius: .8rem; background: #f1f5f9; }
        .tkf-seg label { flex: 1; cursor: pointer; }
        .tkf-seg span { display: block; padding: .65rem 0; border-radius: .65rem; text-align: center; font-size: .875rem; font-weight: 600; color: #475569; }
        .tkf-seg input:checked + span { background: #fff; color: #0f172a; box-shadow: 0 1px 3px rgba(15,23,42,.18); }
        .tkf-seg input:focus-visible + span { outline: 2px solid #2563eb; }
        .tkf-foot { display: flex; align-items: center; justify-content: space-between; gap: .75rem; padding: .75rem 1rem calc(.75rem + env(safe-area-inset-bottom, 0px)); border-top: 1px solid #e2e8f0; background: #f8fafc; }
        .tkf-reset { padding: .5rem 0; font-size: .85rem; font-weight: 600; color: #64748b; text-decoration: underline; }
        .tkf-apply { min-width: 8rem; min-height: 2.9rem; border: 0; border-radius: .8rem; background: rgb(var(--brand-800)); color: #fff; font-size: .9rem; font-weight: 600; cursor: pointer; }
        .tkf-apply:active { background: rgb(var(--brand-700)); }
        @keyframes mnm-up { from { transform: translateY(100%); } to { transform: none; } }
        @media (prefers-reduced-motion: reduce) { .mnm-sheet, .tkf-sheet { animation: none; } .mn-ico { transition: none; } }
    }
</style>
