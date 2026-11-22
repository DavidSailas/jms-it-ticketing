{{-- Sidebar preferences (layout, design, colours, spacing, collapsed, closed groups). Kept per person, per browser, in localStorage. --}}
@php($sbKey = 'jms.sidebar.v3.' . auth()->id())
<script>
    (function () {
        var KEY = @js($sbKey);
        var ALLOWED = {
            layout: ['standard', 'stacked', 'floating', 'right', 'top'],
            style: ['classic', 'light', 'navy', 'pill', 'gradient', 'glass', 'tab'],
            density: ['comfortable', 'compact']
        };
        var HEX = /^#[0-9a-f]{6}$/i;
        var DEFAULTS = { layout: 'standard', style: 'classic', accent: 'auto', bg: 'auto', density: 'comfortable', collapsed: false, closed: [] };

        function lum(hex) {
            var n = parseInt(hex.slice(1), 16), c = [(n >> 16) & 255, (n >> 8) & 255, n & 255].map(function (v) {
                v /= 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
            });
            return 0.2126 * c[0] + 0.7152 * c[1] + 0.0722 * c[2];
        }
        function read() {
            var saved = {};
            try { saved = JSON.parse(localStorage.getItem(KEY)) || {}; } catch (e) {}
            var s = Object.assign({}, DEFAULTS, saved);
            Object.keys(ALLOWED).forEach(function (k) { if (ALLOWED[k].indexOf(s[k]) === -1) s[k] = DEFAULTS[k]; });
            ['accent', 'bg'].forEach(function (k) { if (s[k] !== 'auto' && !HEX.test(s[k])) s[k] = 'auto'; });
            s.collapsed = s.collapsed === true;
            s.closed = Array.isArray(s.closed) ? s.closed : [];
            return s;
        }
        function paint(s) {
            var r = document.documentElement, st = r.style;
            r.dataset.sbLayout = s.layout;
            r.dataset.sbStyle = s.style;
            r.dataset.sbDensity = s.density;
            // Only the standard layout can shrink to an icon rail.
            r.dataset.sbCollapsed = (s.collapsed && s.layout === 'standard') ? '1' : '0';

            var customBg = s.bg !== 'auto', dark = customBg && lum(s.bg) < 0.4;
            if (customBg) {
                r.dataset.sbBg = 'custom';
                st.setProperty('--sb-bg', s.bg);
                st.setProperty('--sb-fg', dark ? '#f1f5f9' : '#0f172a');
                st.setProperty('--sb-fg2', dark ? 'rgba(241,245,249,.72)' : '#64748b');
                st.setProperty('--sb-hover', dark ? 'rgba(255,255,255,.09)' : 'rgba(15,23,42,.06)');
                st.setProperty('--sb-line', dark ? 'rgba(255,255,255,.14)' : 'rgba(15,23,42,.1)');
                st.setProperty('--sb-active-bg', dark ? 'rgba(255,255,255,.16)' : 'rgba(15,23,42,.08)');
                st.setProperty('--sb-active-fg', dark ? '#ffffff' : '#0f172a');
            } else {
                delete r.dataset.sbBg;
                ['--sb-bg', '--sb-fg', '--sb-fg2', '--sb-hover', '--sb-line', '--sb-active-bg', '--sb-active-fg'].forEach(function (p) { st.removeProperty(p); });
            }
            if (s.accent !== 'auto') {
                r.dataset.sbAccent = 'custom';
                st.setProperty('--sb-accent-c', s.accent);
                st.setProperty('--sb-accent-ink', lum(s.accent) > 0.5 ? '#0f172a' : '#ffffff');
            } else {
                delete r.dataset.sbAccent;
                st.removeProperty('--sb-accent-ink');
                if (customBg) st.setProperty('--sb-accent-c', dark ? '#fbbf24' : '#2563eb'); else st.removeProperty('--sb-accent-c');
            }
        }

        // Apply before the first paint so the sidebar never flashes in the wrong state.
        paint(read());

        document.addEventListener('alpine:init', function () {
            Alpine.store('sidebar', Object.assign(read(), {
                save: function () {
                    paint(this);
                    try {
                        localStorage.setItem(KEY, JSON.stringify({ layout: this.layout, style: this.style, accent: this.accent, bg: this.bg, density: this.density, collapsed: this.collapsed, closed: this.closed }));
                    } catch (e) {}
                },
                set: function (key, value) {
                    if (ALLOWED[key] && ALLOWED[key].indexOf(value) === -1) return;
                    if ((key === 'accent' || key === 'bg') && value !== 'auto' && !HEX.test(value)) return;
                    this[key] = value;
                    this.save();
                },
                toggle: function () { this.collapsed = !this.collapsed; this.save(); },
                isClosed: function (group) { return this.closed.indexOf(group) !== -1; },
                toggleGroup: function (group) {
                    this.closed = this.isClosed(group) ? this.closed.filter(function (g) { return g !== group; }) : this.closed.concat([group]);
                    this.save();
                },
                reset: function () { Object.assign(this, DEFAULTS, { closed: [] }); this.save(); }
            }));
        });
    })();
</script>

<style>
    /* =====================================================================
       Sidebar. One structure, five looks:
         classic  = follows the role colours (white; navy for super admins)
         light    = white for everyone, soft brand-blue highlight
         navy     = flat brand navy for everyone
         pill     = white, rounded pill items, solid brand-navy active pill
         gradient = brand gradient with frosted active item
       Colours come from the brand variables, so a partner company's branding still applies.
       ===================================================================== */
    :root { --sb-w: 16rem; }
    aside.sb { width: var(--sb-w); --sb-role: #2563eb; transition: width .25s ease, transform .25s ease; }
    .sb-main { transition: padding-left .25s ease; }
    @media (min-width: 1024px) { .sb-main { padding-left: var(--sb-w); } }

    /* ---- Brand row: exactly as tall as the top bar, so the two lines meet ---- */
    aside.sb .sb-brand { display: flex; align-items: center; height: 3.5rem; padding: 0 1rem; }
    .sb-brand-link { display: flex; align-items: center; gap: .65rem; min-width: 0; text-decoration: none; border-radius: .6rem; }
    .sb-brand-link:focus-visible { outline: 2px solid #2563eb; outline-offset: 3px; }
    .sb-logo-tile { display: grid; place-items: center; flex: none; width: 2.25rem; height: 2.25rem; padding: .2rem; border-radius: .6rem; background: #fff; box-shadow: 0 0 0 1px rgba(15,23,42,.1); }
    .sb-logo-tile img { max-width: 100%; max-height: 100%; object-fit: contain; }
    .sb-brand-text { min-width: 0; }
    .sb-brand-name { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: .9rem; font-weight: 700; line-height: 1.2; }
    .sb-brand-sub { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: .68rem; font-weight: 600; letter-spacing: .04em; text-transform: uppercase; line-height: 1.3; }

    /* ---- Navigation ---- */
    aside.sb .sb-nav { padding: .5rem .75rem .75rem; overflow-x: hidden; scrollbar-width: none; }
    aside.sb .sb-nav::-webkit-scrollbar { display: none; }
    .sb-heading { display: flex; width: 100%; align-items: center; justify-content: space-between; background: transparent; border: 0; cursor: pointer; text-align: left; padding: 1rem .75rem .35rem !important; border-radius: .5rem; }
    .sb-heading:first-child { padding-top: .5rem !important; }
    .sb-heading svg { width: .85rem; height: .85rem; opacity: 0; transition: opacity .15s ease, transform .2s ease; }
    .sb-heading:hover svg, .sb-heading:focus-visible svg { opacity: 1; }
    .sb-heading.is-closed svg { transform: rotate(-90deg); opacity: .7; }
    .sb-group-closed { display: none; }

    aside.sb .sb-link { position: relative; min-height: 2.5rem; padding-top: 0; padding-bottom: 0; font-size: .875rem; font-weight: 500; transition: background-color .2s ease, color .2s ease, border-color .2s ease; }
    .sb-link:focus-visible { outline: 2px solid #2563eb; outline-offset: 2px; }
    .sb-link.is-active { font-weight: 600; }
    .sb-ico { display: inline-flex; align-items: center; justify-content: center; flex: none; width: 1.25rem; height: 1.25rem; }
    .sb-label { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .sb-tip { display: none; }
    html[data-sb-density="compact"] aside.sb .sb-link { min-height: 2.1rem; font-size: .8125rem; }

    /* ---- Footer ---- */
    .sb-foot { position: relative; padding: .625rem .75rem; }
    .sb-user { display: none; align-items: center; gap: .65rem; min-width: 0; margin-bottom: .25rem; padding: .5rem; border-radius: .9rem; text-decoration: none; transition: background-color .2s ease; }
    .sb-user:focus-visible { outline: 2px solid #2563eb; outline-offset: 2px; }
    .sb-user-text { min-width: 0; }
    .sb-user-name { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: .85rem; font-weight: 600; line-height: 1.25; }
    .sb-user-role { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: .72rem; line-height: 1.3; }
    .sb-customise { display: flex; align-items: center; gap: .75rem; width: 100%; min-height: 2.5rem; padding: 0 .75rem; border: 0; border-radius: .6rem; background: transparent; font-size: .8125rem; font-weight: 500; cursor: pointer; transition: background-color .2s ease; }
    .sb-customise { position: relative; }
    .sb-customise svg { width: 1.15rem; height: 1.15rem; flex: none; }
    .sb-customise:focus-visible { outline: 2px solid #2563eb; outline-offset: 2px; }

    /* ---- Open / close controls ---- */
    .sb-handle { display: none; position: absolute; top: 2.6rem; right: -.9rem; z-index: 45; width: 1.8rem; height: 1.8rem; align-items: center; justify-content: center; border-radius: 9999px; border: 1px solid #e2e8f0; background: #fff; color: #475569; box-shadow: 0 2px 8px rgba(15,23,42,.16); cursor: pointer; transition: background-color .2s ease, color .2s ease, transform .2s ease; }
    .sb-handle svg { width: 1rem; height: 1rem; transition: transform .25s ease; }
    .sb-handle:hover { background: rgb(var(--brand-800)); color: #fff; border-color: rgb(var(--brand-800)); transform: scale(1.08); }
    .sb-handle:focus-visible { outline: 2px solid #2563eb; outline-offset: 2px; }
    @media (min-width: 1024px) { .sb-handle { display: flex; } }
    html[data-sb-collapsed="1"] .sb-handle svg { transform: rotate(180deg); }
    .sb-topbtn { display: none; align-items: center; justify-content: center; width: 2.25rem; height: 2.25rem; margin-left: -.25rem; border: 0; border-radius: .6rem; background: transparent; color: #475569; cursor: pointer; transition: background-color .2s ease, color .2s ease; }
    .sb-topbtn:hover { background: #f1f5f9; color: #0f172a; }
    .sb-topbtn:focus-visible { outline: 2px solid #2563eb; outline-offset: 2px; }
    @media (min-width: 1024px) { .sb-topbtn { display: inline-flex; } }

    /* ---- Collapsed rail (desktop only) ---- */
    @media (min-width: 1024px) {
        html[data-sb-collapsed="1"] { --sb-w: 4.75rem; }
        html[data-sb-collapsed="1"] .sb .sb-label,
        html[data-sb-collapsed="1"] .sb .sb-heading,
        html[data-sb-collapsed="1"] .sb .sb-brand-text { display: none !important; }
        html[data-sb-collapsed="1"] .sb .sb-group-closed { display: block; }
        html[data-sb-collapsed="1"] .sb .sb-group ~ .sb-group { margin-top: .5rem; padding-top: .5rem; border-top: 1px solid rgba(100,116,139,.25); }
        html[data-sb-collapsed="1"] .sb .sb-brand { justify-content: center; padding: 0; }
        html[data-sb-collapsed="1"] .sb .sb-nav { overflow: visible !important; padding-left: .75rem !important; padding-right: .75rem !important; }
        html[data-sb-collapsed="1"] .sb .sb-link { justify-content: center; padding-left: 0 !important; padding-right: 0 !important; gap: 0 !important; }
        html[data-sb-collapsed="1"] .sb .sb-badge { position: absolute; top: .15rem; right: .3rem; min-width: 1rem; height: 1rem; margin: 0 !important; padding: 0 .3rem !important; font-size: 10px !important; line-height: 1rem; text-align: center; }
        html[data-sb-collapsed="1"] .sb .sb-customise:hover .sb-tip,
        html[data-sb-collapsed="1"] .sb .sb-link:hover .sb-tip,
        html[data-sb-collapsed="1"] .sb .sb-link:focus-visible .sb-tip { display: block; }
        .sb-tip { position: absolute; left: calc(100% + .9rem); top: 50%; transform: translateY(-50%); z-index: 70; white-space: nowrap; pointer-events: none; border-radius: .5rem; background: #111827; color: #fff; padding: .35rem .65rem; font-size: .75rem; font-weight: 600; box-shadow: 0 10px 24px -8px rgba(15,23,42,.5); }
        html[data-sb-collapsed="1"] .sb .sb-customise { justify-content: center; padding: 0; gap: 0; }
    }

    /* ---------- Look: LIGHT (white for every role) ---------- */
    html[data-sb-style="light"] aside.sb { background: #fff !important; border-color: #e2e8f0 !important; }
    html[data-sb-style="light"] .sb .sb-divider { border-color: #f1f5f9 !important; }
    html[data-sb-style="light"] .sb .sb-brand-name { color: #0f172a !important; }
    html[data-sb-style="light"] .sb .sb-brand-sub { color: #64748b !important; }
    html[data-sb-style="light"] .sb .sb-link { background: transparent !important; border-color: transparent !important; color: #475569 !important; }
    html[data-sb-style="light"] .sb .sb-link .sb-ico { color: #94a3b8 !important; }
    html[data-sb-style="light"] .sb .sb-link:hover { background: #f1f5f9 !important; color: #0f172a !important; }
    html[data-sb-style="light"] .sb .sb-link.is-active { background: rgb(var(--brand-50)) !important; color: rgb(var(--brand-700)) !important; border-color: rgb(var(--brand-600)) !important; }
    html[data-sb-style="light"] .sb .sb-link.is-active .sb-ico { color: rgb(var(--brand-600)) !important; }
    html[data-sb-style="light"] .sb .sb-heading { color: #94a3b8 !important; }
    html[data-sb-style="light"] .sb .sb-heading:hover { background: #f8fafc; }
    html[data-sb-style="light"] .sb .sb-badge { background: rgb(var(--brand-600)) !important; color: #fff !important; }
    html[data-sb-style="light"] .sb .sb-customise { color: #475569 !important; }
    html[data-sb-style="light"] .sb .sb-customise:hover { background: #f1f5f9 !important; }

    /* ---------- Look: NAVY (brand navy for every role) ---------- */
    html[data-sb-style="navy"] aside.sb { background: rgb(var(--brand-900)) !important; border-color: rgb(var(--brand-800)) !important; }
    html[data-sb-style="navy"] .sb .sb-divider { border-color: rgba(255,255,255,.1) !important; }
    html[data-sb-style="navy"] .sb .sb-brand-name { color: #fff !important; }
    html[data-sb-style="navy"] .sb .sb-brand-sub { color: rgb(var(--brand-200)) !important; }
    html[data-sb-style="navy"] .sb .sb-link { background: transparent !important; border-color: transparent !important; color: rgb(var(--brand-100)) !important; }
    html[data-sb-style="navy"] .sb .sb-link .sb-ico { color: rgb(var(--brand-200) / .75) !important; }
    html[data-sb-style="navy"] .sb .sb-link:hover { background: rgba(255,255,255,.07) !important; color: #fff !important; }
    html[data-sb-style="navy"] .sb .sb-link.is-active { background: rgba(255,255,255,.12) !important; color: #fff !important; border-color: #fbbf24 !important; }
    html[data-sb-style="navy"] .sb .sb-link.is-active .sb-ico { color: #fcd34d !important; }
    html[data-sb-style="navy"] .sb .sb-heading { color: rgb(var(--brand-200) / .65) !important; }
    html[data-sb-style="navy"] .sb .sb-heading:hover { background: rgba(255,255,255,.05); }
    html[data-sb-style="navy"] .sb .sb-badge { background: #fbbf24 !important; color: rgb(var(--brand-900)) !important; }
    html[data-sb-style="navy"] .sb .sb-customise { color: rgb(var(--brand-100)) !important; }
    html[data-sb-style="navy"] .sb .sb-customise:hover { background: rgba(255,255,255,.08) !important; }

    /* ---------- Look: PILL (white, rounded pills, solid navy active) ---------- */
    html[data-sb-style="pill"] aside.sb { background: #fff !important; border-color: #e2e8f0 !important; }
    html[data-sb-style="pill"] .sb .sb-divider { border-color: #f1f5f9 !important; }
    html[data-sb-style="pill"] .sb .sb-brand-name { color: #0f172a !important; }
    html[data-sb-style="pill"] .sb .sb-brand-sub { color: #64748b !important; }
    html[data-sb-style="pill"] .sb .sb-link { background: transparent !important; border-color: transparent !important; border-left-width: 0 !important; border-radius: 9999px !important; color: #475569 !important; padding-left: .875rem; padding-right: .875rem; }
    html[data-sb-style="pill"] .sb .sb-link .sb-ico { color: #94a3b8 !important; }
    html[data-sb-style="pill"] .sb .sb-link:hover { background: #f1f5f9 !important; color: #0f172a !important; }
    html[data-sb-style="pill"] .sb .sb-link.is-active { background: rgb(var(--brand-800)) !important; color: #fff !important; box-shadow: 0 6px 14px -8px rgb(var(--brand-900) / .8); }
    html[data-sb-style="pill"] .sb .sb-link.is-active .sb-ico { color: #fff !important; }
    html[data-sb-style="pill"] .sb .sb-heading { color: #94a3b8 !important; }
    html[data-sb-style="pill"] .sb .sb-heading:hover { background: #f8fafc; }
    html[data-sb-style="pill"] .sb .sb-badge { background: rgb(var(--brand-600)) !important; color: #fff !important; }
    html[data-sb-style="pill"] .sb .sb-link.is-active .sb-badge { background: #fff !important; color: rgb(var(--brand-800)) !important; }
    html[data-sb-style="pill"] .sb .sb-customise { color: #475569 !important; border-radius: 9999px; }
    html[data-sb-style="pill"] .sb .sb-customise:hover { background: #f1f5f9 !important; }

    /* ---------- Look: GRADIENT (brand gradient, frosted active item) ---------- */
    html[data-sb-style="gradient"] aside.sb { background: linear-gradient(180deg, rgb(var(--brand-700)) 0%, rgb(var(--brand-900)) 100%) !important; border-color: rgb(var(--brand-900)) !important; }
    html[data-sb-style="gradient"] .sb .sb-divider { border-color: rgba(255,255,255,.14) !important; }
    html[data-sb-style="gradient"] .sb .sb-brand-name { color: #fff !important; }
    html[data-sb-style="gradient"] .sb .sb-brand-sub { color: rgb(var(--brand-100)) !important; }
    html[data-sb-style="gradient"] .sb .sb-link { background: transparent !important; border-color: transparent !important; border-left-width: 0 !important; border-radius: .75rem !important; color: rgb(var(--brand-100)) !important; padding-left: .875rem; padding-right: .875rem; }
    html[data-sb-style="gradient"] .sb .sb-link .sb-ico { color: rgb(var(--brand-100) / .8) !important; }
    html[data-sb-style="gradient"] .sb .sb-link:hover { background: rgba(255,255,255,.09) !important; color: #fff !important; }
    html[data-sb-style="gradient"] .sb .sb-link.is-active { background: rgba(255,255,255,.18) !important; color: #fff !important; box-shadow: inset 0 0 0 1px rgba(255,255,255,.22), 0 8px 16px -10px rgba(0,0,0,.55); }
    html[data-sb-style="gradient"] .sb .sb-link.is-active .sb-ico { color: #fff !important; }
    html[data-sb-style="gradient"] .sb .sb-heading { color: rgb(var(--brand-100) / .6) !important; }
    html[data-sb-style="gradient"] .sb .sb-heading:hover { background: rgba(255,255,255,.06); }
    html[data-sb-style="gradient"] .sb .sb-badge { background: #fff !important; color: rgb(var(--brand-800)) !important; }
    html[data-sb-style="gradient"] .sb .sb-customise { color: rgb(var(--brand-100)) !important; }
    html[data-sb-style="gradient"] .sb .sb-customise:hover { background: rgba(255,255,255,.1) !important; }

    /* ---------- Look: GLASS (dark glass, rounded icon chips, glowing active item) ---------- */
    html[data-sb-style="glass"] aside.sb { --sb-glow: var(--sb-accent-c, #2563eb); background: radial-gradient(130% 55% at 50% 0%, #2b2e36 0%, #17191e 55%, #0d0f12 100%) !important; border-color: rgba(255,255,255,.08) !important; box-shadow: 0 30px 60px -30px rgba(0,0,0,.75); }
    html[data-sb-style="glass"] .sb .sb-divider { border-color: rgba(255,255,255,.08) !important; }
    html[data-sb-style="glass"] .sb .sb-brand-name { color: #fff !important; }
    html[data-sb-style="glass"] .sb .sb-brand-sub { color: #9ca3af !important; }
    html[data-sb-style="glass"] .sb .sb-link { background: transparent !important; border-color: transparent !important; border-left-width: 0 !important; border-radius: 9999px !important; color: #e5e7eb !important; padding-left: .5rem; padding-right: .75rem; gap: .75rem; min-height: 2.75rem; }
    html[data-sb-style="glass"] .sb .sb-link .sb-ico { width: 2rem; height: 2rem; border-radius: .7rem; background: rgba(255,255,255,.09); color: #d1d5db !important; }
    html[data-sb-style="glass"] .sb .sb-link:hover { background: rgba(255,255,255,.06) !important; color: #fff !important; }
    html[data-sb-style="glass"] .sb .sb-link:hover .sb-ico { background: rgba(255,255,255,.16); color: #fff !important; }
    html[data-sb-style="glass"] .sb .sb-link.is-active { background: var(--sb-glow) !important; color: #fff !important; box-shadow: 0 10px 30px -8px color-mix(in srgb, var(--sb-glow) 75%, transparent); }
    html[data-sb-style="glass"] .sb .sb-link.is-active .sb-ico { background: rgba(255,255,255,.22); color: #fff !important; }
    html[data-sb-style="glass"] .sb .sb-heading { color: #9ca3af !important; }
    html[data-sb-style="glass"] .sb .sb-heading:hover { background: rgba(255,255,255,.05); }
    html[data-sb-style="glass"] .sb .sb-badge { background: var(--sb-glow) !important; color: #fff !important; }
    html[data-sb-style="glass"] .sb .sb-link.is-active .sb-badge { background: #fff !important; color: #111827 !important; }
    html[data-sb-style="glass"] .sb .sb-customise { color: #d1d5db !important; border-radius: 9999px; }
    html[data-sb-style="glass"] .sb .sb-customise:hover { background: rgba(255,255,255,.08) !important; }
    html[data-sb-style="glass"] .sb .sb-user { display: flex; }
    html[data-sb-style="glass"] .sb .sb-user:hover { background: rgba(255,255,255,.06); }
    html[data-sb-style="glass"] .sb .sb-user-name { color: #fff; }
    html[data-sb-style="glass"] .sb .sb-user-role { color: #9ca3af; }
    html[data-sb-style="glass"] .sb-handle { background: #2b2e36; color: #e5e7eb; border-color: rgba(255,255,255,.14); }
    html[data-sb-style="glass"] .sb .sb-tip { background: var(--sb-glow); font-size: .85rem; padding: .55rem 1rem; border-radius: 9999px; box-shadow: 0 10px 30px -8px color-mix(in srgb, var(--sb-glow) 80%, transparent); }
    @media (min-width: 1024px) {
        html[data-sb-style="glass"][data-sb-collapsed="1"] .sb .sb-user-text,
        html[data-sb-style="glass"][data-sb-layout="stacked"] .sb .sb-user-text { display: none; }
        html[data-sb-style="glass"][data-sb-collapsed="1"] .sb .sb-user,
        html[data-sb-style="glass"][data-sb-layout="stacked"] .sb .sb-user { justify-content: center; padding: .35rem 0; }
        html[data-sb-style="glass"][data-sb-collapsed="1"] .sb .sb-link .sb-ico { width: 2.25rem; height: 2.25rem; }
        html[data-sb-style="glass"][data-sb-layout="top"] .sb .sb-user { display: none; }
        html[data-sb-style="glass"][data-sb-layout="top"] .sb .sb-link { padding-left: .4rem !important; }
    }

    /* ---------- Look: TAB (bright brand panel; the active item is a tab that joins the page) ---------- */
    html[data-sb-style="tab"] aside.sb { background: rgb(var(--brand-700)) !important; border-color: rgb(var(--brand-700)) !important; }
    html[data-sb-style="tab"][data-sb-layout="standard"] aside.sb { border-right-width: 0; }
    html[data-sb-style="tab"][data-sb-layout="right"] aside.sb { border-left-width: 0; }
    html[data-sb-style="tab"] .sb .sb-divider { border-color: rgba(255,255,255,.18) !important; }
    html[data-sb-style="tab"] .sb .sb-brand-name { color: #fff !important; }
    html[data-sb-style="tab"] .sb .sb-brand-sub { color: rgb(var(--brand-100)) !important; }
    html[data-sb-style="tab"] .sb .sb-nav { padding-right: 0 !important; }
    html[data-sb-style="tab"] .sb .sb-link { background: transparent !important; border-color: transparent !important; border-left-width: 0 !important; border-radius: 9999px 0 0 9999px !important; color: rgba(255,255,255,.9) !important; padding-left: 1rem; min-height: 2.75rem; }
    html[data-sb-style="tab"] .sb .sb-link .sb-ico { color: rgba(255,255,255,.78) !important; }
    html[data-sb-style="tab"] .sb .sb-link:hover { background: rgba(255,255,255,.12) !important; color: #fff !important; }
    html[data-sb-style="tab"] .sb .sb-link.is-active { background: #f8fafc !important; color: rgb(var(--brand-700)) !important; font-weight: 700; }
    html[data-sb-style="tab"] .sb .sb-link.is-active .sb-ico { color: rgb(var(--brand-700)) !important; }
    html[data-sb-style="tab"] .sb .sb-link.is-active::before,
    html[data-sb-style="tab"] .sb .sb-link.is-active::after { content: ""; position: absolute; right: 0; width: .9rem; height: .9rem; pointer-events: none; }
    html[data-sb-style="tab"] .sb .sb-link.is-active::before { top: -.9rem;    background: radial-gradient(circle at 0 0, transparent .9rem, #f8fafc .9rem); }
    html[data-sb-style="tab"] .sb .sb-link.is-active::after  { bottom: -.9rem; background: radial-gradient(circle at 0 100%, transparent .9rem, #f8fafc .9rem); }
    html[data-sb-style="tab"] .sb .sb-heading { color: rgba(255,255,255,.62) !important; }
    html[data-sb-style="tab"] .sb .sb-heading:hover { background: rgba(255,255,255,.08); }
    html[data-sb-style="tab"] .sb .sb-badge { background: #fff !important; color: rgb(var(--brand-700)) !important; margin-right: 1rem; }
    html[data-sb-style="tab"] .sb .sb-link.is-active .sb-badge { background: rgb(var(--brand-700)) !important; color: #fff !important; }
    html[data-sb-style="tab"] .sb .sb-customise { color: rgba(255,255,255,.9) !important; }
    html[data-sb-style="tab"] .sb .sb-customise:hover { background: rgba(255,255,255,.12) !important; }
    /* Right side: the tab joins the page on the left edge */
    html[data-sb-style="tab"][data-sb-layout="right"] .sb .sb-nav { padding-right: .75rem !important; padding-left: 0 !important; }
    html[data-sb-style="tab"][data-sb-layout="right"] .sb .sb-link { border-radius: 0 9999px 9999px 0 !important; padding-left: .75rem; padding-right: 1rem; }
    html[data-sb-style="tab"][data-sb-layout="right"] .sb .sb-link.is-active::before,
    html[data-sb-style="tab"][data-sb-layout="right"] .sb .sb-link.is-active::after { right: auto; left: 0; }
    html[data-sb-style="tab"][data-sb-layout="right"] .sb .sb-link.is-active::before { background: radial-gradient(circle at 100% 0, transparent .9rem, #f8fafc .9rem); }
    html[data-sb-style="tab"][data-sb-layout="right"] .sb .sb-link.is-active::after  { background: radial-gradient(circle at 100% 100%, transparent .9rem, #f8fafc .9rem); }
    /* Layouts where the panel does not touch the page: a simple frosted item instead of a tab */
    html[data-sb-style="tab"][data-sb-layout="stacked"] .sb .sb-link,
    html[data-sb-style="tab"][data-sb-layout="floating"] .sb .sb-link,
    html[data-sb-style="tab"][data-sb-layout="top"] .sb .sb-link { border-radius: .75rem !important; }
    html[data-sb-style="tab"][data-sb-layout="stacked"] .sb .sb-link.is-active,
    html[data-sb-style="tab"][data-sb-layout="floating"] .sb .sb-link.is-active,
    html[data-sb-style="tab"][data-sb-layout="top"] .sb .sb-link.is-active { background: rgba(255,255,255,.2) !important; color: #fff !important; box-shadow: none; }
    html[data-sb-style="tab"][data-sb-layout="stacked"] .sb .sb-link.is-active .sb-ico,
    html[data-sb-style="tab"][data-sb-layout="floating"] .sb .sb-link.is-active .sb-ico,
    html[data-sb-style="tab"][data-sb-layout="top"] .sb .sb-link.is-active .sb-ico { color: #fff !important; }
    html[data-sb-style="tab"][data-sb-layout="stacked"] .sb .sb-link.is-active::before,
    html[data-sb-style="tab"][data-sb-layout="stacked"] .sb .sb-link.is-active::after,
    html[data-sb-style="tab"][data-sb-layout="floating"] .sb .sb-link.is-active::before,
    html[data-sb-style="tab"][data-sb-layout="floating"] .sb .sb-link.is-active::after,
    html[data-sb-style="tab"][data-sb-layout="top"] .sb .sb-link.is-active::before,
    html[data-sb-style="tab"][data-sb-layout="top"] .sb .sb-link.is-active::after { display: none; }

    /* =====================================================================
       LAYOUTS (desktop only; phones always get the slide-out menu)
       ===================================================================== */
    html[data-sb-layout="stacked"] .sb-handle, html[data-sb-layout="top"] .sb-handle,
    html[data-sb-layout="stacked"] .sb-topbtn, html[data-sb-layout="top"] .sb-topbtn { display: none !important; }

    @media (min-width: 1024px) {
        /* Compact: narrow rail, label under each icon */
        html[data-sb-layout="stacked"] { --sb-w: 5.75rem; }
        html[data-sb-layout="stacked"] .sb .sb-brand { justify-content: center; padding: 0; }
        html[data-sb-layout="stacked"] .sb .sb-brand-text,
        html[data-sb-layout="stacked"] .sb .sb-heading,
        html[data-sb-layout="stacked"] .sb .sb-tip { display: none !important; }
        html[data-sb-layout="stacked"] .sb .sb-nav { padding: .5rem .5rem .75rem; }
        html[data-sb-layout="stacked"] .sb .sb-group ~ .sb-group { margin-top: .5rem; padding-top: .5rem; border-top: 1px solid rgba(100,116,139,.25); }
        html[data-sb-layout="stacked"] .sb .sb-link { flex-direction: column; justify-content: center; gap: .2rem !important; min-height: 3.6rem; padding: .5rem .125rem !important; border-left-width: 0 !important; text-align: center; border-radius: .75rem !important; }
        html[data-sb-layout="stacked"] .sb .sb-label { display: block; max-width: 100%; white-space: normal; overflow-wrap: anywhere; font-size: .66rem; line-height: 1.15; font-weight: 600; }
        html[data-sb-layout="stacked"] .sb .sb-link.is-active { box-shadow: inset 3px 0 0 currentColor; }
        html[data-sb-layout="stacked"] .sb .sb-badge { position: absolute; top: .3rem; right: .45rem; min-width: 1rem; height: 1rem; margin: 0 !important; padding: 0 .3rem !important; font-size: 10px !important; line-height: 1rem; text-align: center; }
        html[data-sb-layout="stacked"] .sb .sb-foot { padding: .5rem; }
        html[data-sb-layout="stacked"] .sb .sb-customise { flex-direction: column; justify-content: center; gap: .15rem; min-height: 3.4rem; padding: .4rem 0; font-size: .66rem; font-weight: 600; }

        /* Floating: detached rounded panel */
        html[data-sb-layout="floating"] aside.sb { top: .75rem; bottom: .75rem; left: .75rem; border-radius: 1.25rem; border-width: 1px; box-shadow: 0 22px 44px -26px rgba(15,23,42,.5); }
        html[data-sb-layout="floating"] .sb-main { padding-left: calc(var(--sb-w) + 1.5rem); }
        html[data-sb-layout="floating"] .sb .sb-brand { border-radius: 1.25rem 1.25rem 0 0; }

        /* Right: sidebar on the right-hand side */
        html[data-sb-layout="right"] aside.sb { left: auto; right: 0; border-right-width: 0; border-left-width: 1px; }
        html[data-sb-layout="right"] .sb-main { padding-left: 0; padding-right: var(--sb-w); }
        html[data-sb-layout="right"] .sb-handle { right: auto; left: -.9rem; }
        html[data-sb-layout="right"] .sb-handle svg { transform: rotate(180deg); }
        html[data-sb-layout="right"][data-sb-collapsed="1"] .sb-handle svg { transform: rotate(0deg); }
        html[data-sb-layout="right"] .sb-tip { left: auto; right: calc(100% + .9rem); }

        /* Top: menu across the top, the page header sits below it */
        html[data-sb-layout="top"] { --sb-w: 0rem; }
        html[data-sb-layout="top"] aside.sb { top: 0; left: 0; right: 0; bottom: auto; width: auto; height: 3.5rem; flex-direction: row; align-items: center; border-right-width: 0; border-bottom-width: 1px; }
        html[data-sb-layout="top"] .sb-main { padding-left: 0; padding-top: 3.5rem; }
        html[data-sb-layout="top"] .sb-main > header { top: 3.5rem; }
        html[data-sb-layout="top"] .sb .sb-brand { flex: none; border-bottom-width: 0; border-right: 1px solid rgba(100,116,139,.25); padding: 0 1rem; }
        html[data-sb-layout="top"] .sb .sb-nav { display: flex; flex-direction: row; align-items: center; gap: .25rem; flex: 1; min-width: 0; overflow-x: auto !important; overflow-y: hidden; padding: 0 .75rem; }
        html[data-sb-layout="top"] .sb .sb-nav > * { margin-top: 0 !important; }
        html[data-sb-layout="top"] .sb .sb-heading { display: none !important; }
        html[data-sb-layout="top"] .sb .sb-group { display: flex; flex-direction: row; align-items: center; gap: .25rem; flex: none; }
        html[data-sb-layout="top"] .sb .sb-group > * { margin-top: 0 !important; }
        html[data-sb-layout="top"] .sb .sb-group-closed { display: flex; }
        html[data-sb-layout="top"] .sb .sb-group ~ .sb-group { margin-left: .5rem; padding-left: .75rem; border-left: 1px solid rgba(100,116,139,.25); }
        html[data-sb-layout="top"] .sb .sb-link { flex: none; min-height: 2.25rem; padding-left: .75rem !important; padding-right: .75rem !important; border-left-width: 0 !important; border-radius: .6rem !important; white-space: nowrap; }
        html[data-sb-layout="top"] .sb .sb-link.is-active { box-shadow: inset 0 -3px 0 currentColor; }
        html[data-sb-layout="top"] .sb .sb-badge { margin-left: .5rem; }
        html[data-sb-layout="top"] .sb .sb-tip { display: none !important; }
        html[data-sb-layout="top"] .sb .sb-foot { flex: none; border-top-width: 0; padding: 0 .75rem; }
        html[data-sb-layout="top"] .sb .sb-customise { width: 2.5rem; padding: 0; justify-content: center; }
        html[data-sb-layout="top"] .sb .sb-customise .sb-label { display: none; }
        html[data-sb-layout="top"] .sb .sb-link { padding-left: .6rem !important; padding-right: .6rem !important; }
    }

    @media (min-width: 1024px) and (max-width: 1599px) { html[data-sb-layout="top"] .sb .sb-brand-text { display: none; } }

    /* =====================================================================
       COLOURS you choose yourself (Customise > Colours)
       ===================================================================== */
    html[data-sb-bg="custom"] aside.sb { background: var(--sb-bg) !important; border-color: var(--sb-line) !important; }
    html[data-sb-bg="custom"] .sb .sb-divider { border-color: var(--sb-line) !important; }
    html[data-sb-bg="custom"] .sb .sb-brand-name { color: var(--sb-fg) !important; }
    html[data-sb-bg="custom"] .sb .sb-brand-sub { color: var(--sb-fg2) !important; }
    html[data-sb-bg="custom"] .sb .sb-link { background: transparent !important; border-color: transparent !important; color: var(--sb-fg) !important; }
    html[data-sb-bg="custom"] .sb .sb-link .sb-ico { color: var(--sb-fg2) !important; }
    html[data-sb-bg="custom"] .sb .sb-link:hover { background: var(--sb-hover) !important; }
    html[data-sb-bg="custom"] .sb .sb-link.is-active { background: var(--sb-active-bg) !important; color: var(--sb-active-fg) !important; border-color: var(--sb-accent-c, #fbbf24) !important; box-shadow: none; }
    html[data-sb-bg="custom"] .sb .sb-link.is-active .sb-ico { color: var(--sb-accent-c, #fbbf24) !important; }
    html[data-sb-bg="custom"] .sb .sb-heading { color: var(--sb-fg2) !important; }
    html[data-sb-bg="custom"] .sb .sb-heading:hover { background: var(--sb-hover); }
    html[data-sb-bg="custom"] .sb .sb-badge { background: var(--sb-accent-c, #fbbf24) !important; color: #0f172a !important; }
    html[data-sb-bg="custom"] .sb .sb-customise { color: var(--sb-fg) !important; }
    html[data-sb-bg="custom"] .sb .sb-customise:hover { background: var(--sb-hover) !important; }
    html[data-sb-bg="custom"][data-sb-layout="top"] .sb .sb-link.is-active { box-shadow: inset 0 -3px 0 var(--sb-accent-c, #fbbf24); }
    html[data-sb-bg="custom"][data-sb-layout="stacked"] .sb .sb-link.is-active { box-shadow: inset 3px 0 0 var(--sb-accent-c, #fbbf24); }

    html[data-sb-accent="custom"] .sb .sb-link.is-active { border-color: var(--sb-accent-c) !important; }
    html[data-sb-accent="custom"] .sb .sb-link.is-active .sb-ico { color: var(--sb-accent-c) !important; }
    html[data-sb-accent="custom"] .sb .sb-badge { background: var(--sb-accent-c) !important; color: var(--sb-accent-ink) !important; }
    html:not([data-sb-bg="custom"])[data-sb-accent="custom"][data-sb-style="light"] .sb .sb-link.is-active,
    html:not([data-sb-bg="custom"])[data-sb-accent="custom"][data-sb-style="classic"] aside.sb:not([data-role="super_admin"]) .sb-link.is-active { background: color-mix(in srgb, var(--sb-accent-c) 12%, #fff) !important; color: var(--sb-accent-c) !important; }
    html:not([data-sb-bg="custom"])[data-sb-accent="custom"][data-sb-style="pill"] .sb .sb-link.is-active { background: var(--sb-accent-c) !important; color: var(--sb-accent-ink) !important; }
    html:not([data-sb-bg="custom"])[data-sb-accent="custom"][data-sb-style="pill"] .sb .sb-link.is-active .sb-ico { color: var(--sb-accent-ink) !important; }
    html[data-sb-accent="custom"] .sb-handle:hover { background: var(--sb-accent-c); border-color: var(--sb-accent-c); color: var(--sb-accent-ink); }
    html[data-sb-accent="custom"][data-sb-bg="custom"] .sb .sb-link.is-active { background: var(--sb-active-bg) !important; color: var(--sb-active-fg) !important; }
    html[data-sb-accent="custom"][data-sb-layout="top"] .sb .sb-link.is-active { box-shadow: inset 0 -3px 0 var(--sb-accent-c); }
    html[data-sb-accent="custom"][data-sb-layout="stacked"] .sb .sb-link.is-active { box-shadow: inset 3px 0 0 var(--sb-accent-c); }

    html[data-sb-bg="custom"][data-sb-style="tab"][data-sb-layout="standard"] .sb .sb-link.is-active,
    html[data-sb-bg="custom"][data-sb-style="tab"][data-sb-layout="right"] .sb .sb-link.is-active { background: #f8fafc !important; color: #0f172a !important; border-color: transparent !important; }
    html[data-sb-style="tab"][data-sb-accent="custom"][data-sb-layout="standard"] .sb .sb-link.is-active,
    html[data-sb-style="tab"][data-sb-accent="custom"][data-sb-layout="right"] .sb .sb-link.is-active,
    html[data-sb-style="tab"][data-sb-accent="custom"][data-sb-layout="standard"] .sb .sb-link.is-active .sb-ico,
    html[data-sb-style="tab"][data-sb-accent="custom"][data-sb-layout="right"] .sb .sb-link.is-active .sb-ico { color: var(--sb-accent-c) !important; }
    html[data-sb-bg="custom"][data-sb-style="glass"] .sb .sb-user-name { color: var(--sb-fg); }
    html[data-sb-bg="custom"][data-sb-style="glass"] .sb .sb-user-role { color: var(--sb-fg2); }

    /* ---- Customise panel ---- */
    /* The panel is moved to <body> (x-teleport) so it is never clipped by, or positioned inside, the sidebar.
       Phones and tablets (< 1024px): a bottom sheet. Laptops and desktops: a floating panel next to the sidebar. */
    .sbp-root { position: fixed; inset: 0; z-index: 80; }
    .sbp-backdrop { position: absolute; inset: 0; background: rgba(15,23,42,.5); }
    .sb-pop { position: fixed; z-index: 81; display: flex; flex-direction: column; width: 20rem; max-width: calc(100vw - 1.5rem); max-height: calc(100vh - 2rem); max-height: calc(100dvh - 2rem); overflow: hidden; background: #fff; color: #0f172a; border: 1px solid #e2e8f0; border-radius: 1rem; box-shadow: 0 24px 60px -20px rgba(15,23,42,.4); text-align: left; font-size: .8125rem; animation: sbp-in .2s ease-out; }
    .sbp-body { flex: 1 1 auto; min-height: 0; overflow-y: auto; overscroll-behavior: contain; -webkit-overflow-scrolling: touch; padding: 0 1rem 1rem; scrollbar-width: thin; scrollbar-color: #cbd5e1 transparent; }
    .sbp-body::-webkit-scrollbar { width: 8px; }
    .sbp-body::-webkit-scrollbar-track { background: transparent; }
    .sbp-body::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 9999px; border: 2px solid #fff; }
    .sbp-body::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
    .sbp-grab { display: none; width: 2.5rem; height: .3rem; margin: .5rem auto 0; border-radius: 9999px; background: #cbd5e1; }
    .sbp-foot { display: flex; align-items: center; justify-content: space-between; gap: .75rem; flex: none; padding: .7rem 1rem; border-top: 1px solid #e2e8f0; background: #f8fafc; }
    .sbp-done { border: 0; border-radius: .6rem; background: rgb(var(--brand-800)); color: #fff; padding: .5rem 1.1rem; font-size: .8125rem; font-weight: 600; cursor: pointer; }
    .sbp-done:hover { background: rgb(var(--brand-700)); }
    .sbp-done:focus-visible, .sbp-x:focus-visible, .sbp-reset:focus-visible { outline: 2px solid #2563eb; outline-offset: 2px; }
    @keyframes sbp-in { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: none; } }

    @media (min-width: 1024px) {
        .sbp-backdrop { background: transparent; }
        .sb-pop { left: calc(var(--sb-w) + 1rem); bottom: 1rem; transition: left .25s ease; }
        html[data-sb-layout="floating"] .sb-pop { left: calc(var(--sb-w) + 1.75rem); bottom: 1.5rem; }
        html[data-sb-layout="right"] .sb-pop { left: auto; right: calc(var(--sb-w) + 1rem); }
        html[data-sb-layout="top"] .sb-pop { left: auto; right: 1rem; bottom: auto; top: 4.25rem; max-height: calc(100vh - 5.25rem); max-height: calc(100dvh - 5.25rem); }
    }
    @media (max-width: 1023px) {
        .sb-pop { left: 0; right: 0; bottom: 0; width: auto; max-width: 32rem; margin: 0 auto; max-height: 90vh; max-height: 90dvh; border-radius: 1.25rem 1.25rem 0 0; border-bottom-width: 0; animation-name: sbp-sheet; }
        .sbp-grab { display: block; }
        .sbp-foot { padding-bottom: calc(.7rem + env(safe-area-inset-bottom, 0px)); }
        body.sb-lock { overflow: hidden; }
        .sbp-row { display: none; }   /* "Icons only" is a laptop/desktop option */
    }
    @keyframes sbp-sheet { from { transform: translateY(100%); } to { transform: none; } }
    @media (prefers-reduced-motion: reduce) { .sb-pop { animation: none !important; } }
    .sbp-head { display: flex; flex: none; align-items: center; justify-content: space-between; gap: .75rem; padding: .85rem 1rem .5rem; }
    .sbp-title { font-size: .95rem; font-weight: 700; }
    .sbp-x { display: inline-grid; place-items: center; width: 2rem; height: 2rem; border: 0; background: transparent; cursor: pointer; color: #64748b; border-radius: .5rem; }
    .sbp-x:hover { background: #f1f5f9; }
    .sbp-label { margin: .75rem 0 .35rem; font-size: .68rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: #64748b; }
    .sbp-styles { display: grid; grid-template-columns: repeat(var(--cols, 3), 1fr); gap: .4rem; }
    .sbp-style { border: 1.5px solid #e2e8f0; border-radius: .7rem; padding: .3rem .2rem; background: #fff; cursor: pointer; text-align: center; font-size: .66rem; font-weight: 600; color: #334155; }
    .sbp-style:hover { border-color: #94a3b8; }
    .sbp-style[aria-pressed="true"] { border-color: #1e3a5f; box-shadow: 0 0 0 3px rgba(30,58,95,.12); }
    .sbp-mini { display: flex; flex-direction: column; gap: .2rem; height: 2.5rem; padding: .3rem; margin-bottom: .3rem; border-radius: .5rem; overflow: hidden; background: #fff; box-shadow: inset 0 0 0 1px #e2e8f0; }
    .sbp-mini b { display: block; height: .34rem; border-radius: .2rem; background: #e2e8f0; }
    .sbp-mini.m-classic b.on, .sbp-mini.m-light b.on { background: #dbeafe; box-shadow: inset 3px 0 0 #2563eb; }
    .sbp-mini.m-classic { background: linear-gradient(90deg, #fff 70%, #1e3a5f 70%); }
    .sbp-mini.m-navy { background: #12284a; box-shadow: none; }
    .sbp-mini.m-navy b { background: rgba(255,255,255,.18); }
    .sbp-mini.m-navy b.on { background: rgba(255,255,255,.3); box-shadow: inset 3px 0 0 #fbbf24; }
    .sbp-mini.m-pill b { border-radius: 9999px; }
    .sbp-mini.m-pill b.on { background: #1e3a5f; }
    .sbp-mini.m-gradient { background: linear-gradient(180deg, #2c5282, #12284a); box-shadow: none; }
    .sbp-mini.m-gradient b { background: rgba(255,255,255,.2); border-radius: .3rem; }
    .sbp-mini.m-gradient b.on { background: rgba(255,255,255,.42); box-shadow: inset 0 0 0 1px rgba(255,255,255,.3); }
    .sbp-lay { position: relative; display: block; height: 2.3rem; margin-bottom: .3rem; border-radius: .5rem; background: #f1f5f9; box-shadow: inset 0 0 0 1px #e2e8f0; overflow: hidden; }
    .sbp-lay i { position: absolute; display: block; background: #1e3a5f; border-radius: .12rem; }
    .sbp-lay.l-standard i { left: 0; top: 0; bottom: 0; width: 30%; border-radius: 0; }
    .sbp-lay.l-stacked i { left: 0; top: 0; bottom: 0; width: 16%; border-radius: 0; }
    .sbp-lay.l-floating i { left: .2rem; top: .2rem; bottom: .2rem; width: 28%; border-radius: .35rem; }
    .sbp-lay.l-right i { right: 0; top: 0; bottom: 0; width: 30%; border-radius: 0; }
    .sbp-lay.l-top i { left: 0; right: 0; top: 0; height: 24%; border-radius: 0; }
    .sbp-swatches { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; }
    .sbp-sw { display: inline-block; flex: none; width: 1.6rem; height: 1.6rem; padding: 0; border-radius: 9999px; border: 2px solid #fff; box-shadow: 0 0 0 1px #cbd5e1; cursor: pointer; transition: transform .15s ease; }
    .sbp-sw:hover { transform: scale(1.1); }
    .sbp-sw[aria-pressed="true"] { box-shadow: 0 0 0 2px #1e3a5f; }
    .sbp-sw.sbp-auto { background: conic-gradient(#2563eb, #7c3aed, #f59e0b, #111827, #2563eb); }
    .sbp-pick { position: relative; display: inline-block; overflow: hidden; background: conic-gradient(red, yellow, lime, aqua, blue, magenta, red); }
    .sbp-pick input { position: absolute; inset: -.5rem; width: 3rem; height: 3rem; opacity: 0; cursor: pointer; }
    .sbp-mini.m-glass { background: linear-gradient(180deg, #2b2e36, #0d0f12); box-shadow: none; }
    .sbp-mini.m-glass b { background: rgba(255,255,255,.16); border-radius: 9999px; }
    .sbp-mini.m-glass b.on { background: #2563eb; box-shadow: 0 0 8px rgba(37,99,235,.8); }
    .sbp-mini.m-tab { background: #2c5282; box-shadow: none; padding-right: 0; }
    .sbp-mini.m-tab b { background: rgba(255,255,255,.28); }
    .sbp-mini.m-tab b.on { background: #f8fafc; border-radius: 9999px 0 0 9999px; }
    .sbp-seg { display: flex; padding: .2rem; border-radius: .65rem; background: #f1f5f9; }
    .sbp-seg button { flex: 1; border: 0; border-radius: .5rem; background: transparent; padding: .4rem .5rem; font-size: .75rem; font-weight: 600; color: #475569; cursor: pointer; }
    .sbp-seg button[aria-pressed="true"] { background: #fff; color: #0f172a; box-shadow: 0 1px 3px rgba(15,23,42,.15); }
    .sbp-row { display: flex; align-items: center; justify-content: space-between; gap: .75rem; margin-top: .75rem; font-weight: 600; }
    .sbp-switch { position: relative; width: 2.4rem; height: 1.4rem; border-radius: 9999px; border: 0; background: #cbd5e1; cursor: pointer; transition: background-color .2s ease; }
    .sbp-switch::after { content: ""; position: absolute; top: .2rem; left: .2rem; width: 1rem; height: 1rem; border-radius: 9999px; background: #fff; transition: transform .2s ease; }
    .sbp-switch[aria-checked="true"] { background: #1e3a5f; }
    .sbp-switch[aria-checked="true"]::after { transform: translateX(1rem); }
    .sbp-reset { border: 0; background: transparent; padding: .25rem 0; font-size: .75rem; font-weight: 600; color: #64748b; cursor: pointer; text-decoration: underline; }
    .sbp-note { margin-top: .6rem; font-size: .7rem; color: #64748b; }

    /* =====================================================================
       RESPONSIVE SHELL
       ===================================================================== */
    /* Phones / small tablets: the slide-out menu never wider than the screen, with bigger touch targets */
    @media (max-width: 1023px) {
        aside.sb { width: min(18rem, 88vw); box-shadow: 0 0 40px rgba(15,23,42,.35); }
        aside.sb .sb-nav { overscroll-behavior: contain; }
        aside.sb .sb-link { min-height: 2.75rem; }
        aside.sb .sb-customise { min-height: 2.75rem; }
    }
    /* Small laptops (1024-1279px): slightly slimmer menu so the page keeps its room */
    @media (min-width: 1024px) and (max-width: 1279px) { :root { --sb-w: 14.5rem; } }
    /* Short laptop screens (e.g. 1366x768): tighter rows so the whole menu fits without scrolling */
    @media (min-width: 1024px) and (max-height: 760px) {
        html[data-sb-density="comfortable"][data-sb-layout="standard"] aside.sb .sb-link { min-height: 2.25rem; }
        html[data-sb-density="comfortable"] .sb-heading { padding-top: .6rem !important; }
    }
    /* Touch: comfortable tap sizes inside the Customise panel */
    @media (max-width: 1023px), (pointer: coarse) {
        .sbp-x { width: 2.5rem; height: 2.5rem; }
        .sbp-swatches { display: grid; grid-template-columns: repeat(8, 1fr); gap: .4rem; justify-items: center; }
        .sbp-sw { width: 100%; max-width: 2.5rem; height: auto; aspect-ratio: 1; }
        .sbp-seg button { min-height: 2.6rem; font-size: .8125rem; }
        .sbp-style { padding: .4rem .2rem; font-size: .7rem; }
        .sbp-switch { width: 3rem; height: 1.75rem; }
        .sbp-switch::after { width: 1.35rem; height: 1.35rem; top: .2rem; left: .2rem; }
        .sbp-switch[aria-checked="true"]::after { transform: translateX(1.25rem); }
        .sbp-reset { padding: .6rem 0; }
        .sbp-done { padding: .65rem 1.4rem; }
    }
    @media (prefers-reduced-motion: reduce) { aside.sb, .sb-main, .sb-link, .sb-handle { transition: none !important; } }
</style>
