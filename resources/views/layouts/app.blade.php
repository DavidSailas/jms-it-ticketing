@php
    $role = auth()->user()->role;
    $theme = \App\Support\RoleTheme::for($role);
    $isUser = $role === 'user';
    // Each company can have its own name, logo and colour; JMS staff and companies without a logo see the standard JMS logo.
    $company   = auth()->user()->companyRecord;
    $brandLogo = $company?->logoUrl() ?? asset('images/logo.png');
    $brandName = $company?->name ?? 'JMS One IT';
    $brandCss  = \App\Support\Branding::css($company?->brand_color);
    $all = ['user', 'it_support', 'admin', 'super_admin'];
    $staff = ['it_support', 'admin', 'super_admin'];
    // Admins get the live "Waiting for acceptance" queue (shared by the sidebar, dashboard and ticket list).
    $pendingFeed = auth()->user()->canDispatchJms() ? \App\Support\PendingTickets::feed() : null;
    // Staff see how many bookings are scheduled for today next to "Schedule".
    $todayBookings = in_array($role, $staff)
        ? \App\Models\Ticket::bookings(auth()->user())->whereIn('status', \App\Models\Ticket::ACTIVE)->whereDate('scheduled_for', today())->count()
        : 0;
    $pendingConfig = $pendingFeed ? ['initial' => $pendingFeed, 'url' => route('tickets.pending-feed'), 'pollMs' => 8000] : null;
    $icons = [
        'palette' => '<path d="M12 3a9 9 0 1 0 0 18c1.1 0 1.8-.9 1.5-1.9-.3-1 .4-2.1 1.5-2.1H17a4 4 0 0 0 4-4c0-5-4-10-9-10z"/><path d="M7.5 11.5h.01M10 7.5h.01M14.5 7.5h.01"/>',
        'building' => '<path d="M4 21V5a1 1 0 0 1 1-1h8a1 1 0 0 1 1 1v16M14 9h5a1 1 0 0 1 1 1v11M2 21h20M8 8h2M8 12h2M8 16h2"/>',
        'grid'    => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
        'plus'    => '<circle cx="12" cy="12" r="9"/><path d="M12 8v8M8 12h8"/>',
        'list'    => '<path d="M8 6h13M8 12h13M8 18h13M3.5 6h.01M3.5 12h.01M3.5 18h.01"/>',
        'users'   => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0M16 4.5a3.5 3.5 0 0 1 0 7M18 14a6.5 6.5 0 0 1 3.5 6"/>',
        'calendar' => '<rect x="3" y="4.5" width="18" height="16.5" rx="2"/><path d="M3 9.5h18M8 2.5v4M16 2.5v4"/>',
        'shield'  => '<path d="M12 3l8 3v6c0 4.5-3.2 8.2-8 9-4.8-.8-8-4.5-8-9V6l8-3z"/><path d="M9 12l2 2 4-4"/>',
        'chart'   => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
        'book'    => '<path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v15H6.5A2.5 2.5 0 0 0 4 20.5v-15zM4 20.5A2.5 2.5 0 0 0 6.5 23H20v-5"/>',
        'chat'    => '<path d="M21 12a8 8 0 0 1-11.6 7.1L3 20.5l1.5-4.6A8 8 0 1 1 21 12z"/><path d="M8.5 11h7M8.5 14h4"/>',
        'profile' => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="10" r="3"/><path d="M6.5 18.5a6 6 0 0 1 11 0"/>',
    ];
    // A nav entry is shown by account type (an array) or by permission (a string such as 'users.manage').
    $navAllowed = fn ($who) => is_string($who) ? auth()->user()->hasPermission($who) : in_array($role, $who);
    // [label, route, active-patterns, who, icon, group]
    $nav = [
        [$isUser ? 'New Request' : 'Dashboard', 'dashboard', $isUser ? 'dashboard|tickets.create' : 'dashboard', $all, $isUser ? 'plus' : 'grid', 'main'],
        [$isUser ? 'My Tickets' : ($role === 'it_support' ? 'My Assignments' : 'All Tickets'), 'tickets.index', 'tickets.index|tickets.show', $all, 'list', 'main'],
        ['Schedule', 'schedule.index', 'schedule.*', $staff, 'calendar', 'main'],
        ['Saved Replies', 'canned-replies.index', 'canned-replies.*', $staff, 'chat', 'main'],
        ['Companies', 'companies.index', 'companies.*', 'companies.manage', 'building', 'manage'],
        ['Users', 'users.index', 'users.*', 'users.manage', 'users', 'manage'],
        ['Roles', 'roles.index', 'roles.*', 'roles.manage', 'shield', 'manage'],
        ['Reports', 'reports.index', 'reports.*', 'reports.view', 'chart', 'manage'],
        ['Workflow Guide', 'workflow.index', 'workflow.*', ['super_admin'], 'book', 'manage'],
    ];

    // Ctrl+K search: "Go to" shortcuts for this role (the same pages as the sidebar, plus a few quick filters) and the
    // "keep looking" links that open the full lists with the search already typed in.
    $searchPages = collect($nav)
        ->filter(fn ($n) => $navAllowed($n[3]))
        ->map(fn ($n) => ['title' => $n[0], 'url' => route($n[1], absolute: false), 'icon' => $icons[$n[4]] ?? ''])
        ->concat([
            ['title' => 'Open tickets', 'url' => route('tickets.index', ['status' => 'open'], false), 'icon' => $icons['list']],
            ['title' => 'In progress', 'url' => route('tickets.index', ['status' => 'in_progress'], false), 'icon' => $icons['list']],
            ['title' => 'Resolved tickets', 'url' => route('tickets.index', ['status' => 'resolved'], false), 'icon' => $icons['list']],
            ['title' => 'Notifications', 'url' => route('notifications.index', absolute: false), 'icon' => $icons['list']],
            ['title' => 'My profile', 'url' => route('profile.edit', absolute: false), 'icon' => $icons['profile']],
        ])
        ->when(auth()->user()->canDispatchJms(), fn ($c) => $c->push(
            ['title' => 'Waiting for acceptance', 'url' => route('tickets.index', ['status' => 'unassigned'], false), 'icon' => $icons['list']]
        ))
        ->unique('url')->values()->all();
    $searchMore = array_values(array_filter([
        ['title' => 'Search all tickets for "{q}"', 'url' => route('tickets.index', absolute: false) . '?search='],
        auth()->user()->hasPermission('users.manage') ? ['title' => 'Search users for "{q}"', 'url' => route('users.index', absolute: false) . '?search='] : null,
    ]));
    $searchConfig = ['url' => route('search'), 'pages' => $searchPages, 'more' => $searchMore];
    // Sidebar: grouped menu, and the text for the brand row and footer chip.
    $navGroups = collect($nav)->filter(fn ($n) => $navAllowed($n[3]))->groupBy(5);
    $nameCls   = $role === 'super_admin' ? 'text-white' : 'text-slate-900';
    $subCls    = $role === 'super_admin' ? 'text-brand-200' : 'text-slate-500';
    $brandSub  = $theme['icon'] ? $theme['label'] . ' Console' : ($company ? 'Partner portal' : auth()->user()->roleLabel());
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-sb-layout="standard" data-sb-style="classic" data-sb-density="comfortable" data-sb-collapsed="0">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $brandName }}</title>
    <link rel="icon" href="{{ $brandLogo }}">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet">
    @if ($realtime = \App\Support\Realtime::clientConfig(auth()->user()))
        <script>window.JMS_REALTIME = @json($realtime);</script>
    @endif
    @include('layouts.partials.sidebar-assets')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @if ($brandCss)
        <style>{!! $brandCss !!}</style>
    @endif
</head>
<body class="font-sans antialiased bg-slate-50 text-slate-800" x-data="{ open: false }"
      @keydown.window="if (['INPUT','TEXTAREA','SELECT'].includes($event.target.tagName) || $event.target.isContentEditable || $event.ctrlKey || $event.metaKey || $event.altKey) return; if ($event.key === '[') { $store.sidebar.toggle() }">

    <a href="#main-content" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-lg focus:bg-white focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-brand-800 focus:shadow-lg">Skip to content</a>

    @if ($pendingFeed)
        {{-- Starts the live queue first, so everything below renders with real numbers. --}}
        <div x-data x-init="$store.pending.start(@js($pendingConfig))" hidden></div>
    @endif

    {{-- Mobile overlay --}}
    <div x-show="open" x-cloak @click="open = false" class="fixed inset-0 z-30 bg-slate-900/50 lg:hidden"></div>

    {{-- Sidebar. Look = the person's chosen style (Classic / Soft / Notch) over the role colours (App\Support\RoleTheme).
         Preferences live in the "sidebar" store (see layouts/partials/sidebar-assets). --}}
    <aside :class="open ? 'translate-x-0' : '-translate-x-full'" data-role="{{ $role }}" aria-label="Sidebar"
           class="sb fixed inset-y-0 left-0 z-40 flex flex-col border-r {{ $theme['aside'] }} transition-transform lg:translate-x-0">
        {{-- Open / close handle on the edge of the sidebar (desktop). Same as the top bar button and the [ key. --}}
        <button type="button" @click="$store.sidebar.toggle()" class="sb-handle"
                :aria-label="$store.sidebar.collapsed ? 'Open sidebar' : 'Close sidebar'"
                :aria-expanded="(! $store.sidebar.collapsed).toString()"
                :title="$store.sidebar.collapsed ? 'Open sidebar  ( [ )' : 'Close sidebar  ( [ )'">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 6-6 6 6 6"/></svg>
        </button>
        <div class="sb-brand sb-divider border-b {{ $theme['divider'] }}">
            <a href="{{ route('dashboard') }}" class="sb-brand-link" aria-label="{{ $brandName }}: go to the dashboard">
                <span class="sb-logo-tile"><img src="{{ $brandLogo }}" alt=""></span>
                <span class="sb-brand-text">
                    <span class="sb-brand-name {{ $nameCls }}">{{ $brandName }}</span>
                    <span class="sb-brand-sub {{ $subCls }}">{{ $brandSub }}</span>
                </span>
            </a>
        </div>

        <nav class="sb-nav flex-1 space-y-1 overflow-y-auto p-4" aria-label="Main">
            @foreach ($navGroups as $group => $items)
                @if ($theme['headings'])
                    <button type="button" @click="$store.sidebar.toggleGroup('{{ $group }}')"
                            :class="{ 'is-closed': $store.sidebar.isClosed('{{ $group }}') }"
                            :aria-expanded="(! $store.sidebar.isClosed('{{ $group }}')).toString()"
                            class="sb-heading px-3 pb-1 pt-4 text-[11px] font-semibold uppercase tracking-wider first:pt-0 {{ $theme['heading'] }}">
                        <span>{{ $theme['groups'][$group] ?? '' }}</span>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                    </button>
                @endif
                <div class="sb-group space-y-1" @if ($theme['headings']) :class="{ 'sb-group-closed': $store.sidebar.isClosed('{{ $group }}') }" @endif>
                    @foreach ($items as [$label, $route, $pattern, $roles, $icon, $grp])
                        <?php $active = request()->routeIs(explode('|', $pattern)); ?>
                        <a href="{{ route($route) }}" @if ($active) aria-current="page" @endif
                           class="sb-link flex items-center gap-3 rounded-lg border-l-4 px-3 py-2.5 text-sm font-medium transition {{ $active ? 'is-active ' . $theme['navActive'] : $theme['navIdle'] }}">
                            <span class="sb-ico {{ $active ? $theme['iconActive'] : $theme['iconIdle'] }}">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $icons[$icon] !!}</svg>
                            </span>
                            <span class="sb-label">{{ $label }}</span>
                            @if ($route === 'tickets.index' && $pendingFeed)
                                <span x-data x-show="$store.pending.count > 0" x-cloak
                                      x-text="$store.pending.count > 99 ? '99+' : $store.pending.count"
                                      class="sb-badge ml-auto rounded-full bg-violet-600 px-2 py-0.5 text-xs font-semibold text-white"
                                      title="Waiting for acceptance" aria-label="Tickets waiting for acceptance"></span>
                            @endif
                            @if ($route === 'schedule.index' && $todayBookings > 0)
                                <span class="sb-badge ml-auto rounded-full px-2 py-0.5 text-xs font-semibold {{ $theme['count'] }}"
                                      title="Booked for today" aria-label="{{ $todayBookings }} booked for today">{{ $todayBookings > 99 ? '99+' : $todayBookings }}</span>
                            @endif
                            <span class="sb-tip" role="tooltip">{{ $label }}</span>
                        </a>
                    @endforeach
                </div>
            @endforeach
        </nav>

        {{-- Customise --}}
        <div class="sb-foot sb-divider border-t {{ $theme['divider'] }}" x-data="{ pop: false }" @keydown.escape.window="pop = false">
            {{-- Profile chip: shown by the Glass design only --}}
            <a href="{{ route('profile.edit') }}" class="sb-user" title="My profile">
                <x-avatar :user="auth()->user()" :zoom="false" />
                <span class="sb-user-text">
                    <span class="sb-user-name">{{ auth()->user()->name }}</span>
                    <span class="sb-user-role">{{ auth()->user()->roleLabel() }}</span>
                </span>
            </a>
            <button type="button" @click="pop = ! pop" :aria-expanded="pop.toString()" aria-haspopup="dialog"
                    class="sb-customise {{ $theme['navIdle'] }}" title="Customise sidebar">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 6h10M18 6h2M4 12h4M12 12h8M4 18h12M20 18h0"/><circle cx="16" cy="6" r="2"/><circle cx="10" cy="12" r="2"/><circle cx="18" cy="18" r="2"/></svg>
                <span class="sb-label">Customise</span>
                <span class="sb-tip" role="tooltip">Customise</span>
            </button>

            <div x-show="pop" x-cloak x-transition.opacity.duration.150ms @click.outside="pop = false" class="sb-pop" role="dialog" aria-label="Customise sidebar">
                <div class="sbp-head">
                    <span class="sbp-title">Customise sidebar</span>
                    <button type="button" class="sbp-x" @click="pop = false" aria-label="Close">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
                    </button>
                </div>

                <p class="sbp-label" style="margin-top:0">Layout</p>
                <div class="sbp-styles">
                    <button type="button" class="sbp-style" title="Menu on the left with labels beside the icons" @click="$store.sidebar.set('layout', 'standard')" :aria-pressed="($store.sidebar.layout === 'standard').toString()">
                        <span class="sbp-lay l-standard"><i></i></span>Standard
                    </button>
                    <button type="button" class="sbp-style" title="Narrow bar with the label under each icon" @click="$store.sidebar.set('layout', 'stacked')" :aria-pressed="($store.sidebar.layout === 'stacked').toString()">
                        <span class="sbp-lay l-stacked"><i></i></span>Compact
                    </button>
                    <button type="button" class="sbp-style" title="A detached rounded panel" @click="$store.sidebar.set('layout', 'floating')" :aria-pressed="($store.sidebar.layout === 'floating').toString()">
                        <span class="sbp-lay l-floating"><i></i></span>Floating
                    </button>
                    <button type="button" class="sbp-style" title="Menu on the right-hand side" @click="$store.sidebar.set('layout', 'right')" :aria-pressed="($store.sidebar.layout === 'right').toString()">
                        <span class="sbp-lay l-right"><i></i></span>Right
                    </button>
                    <button type="button" class="sbp-style" title="Menu across the top" @click="$store.sidebar.set('layout', 'top')" :aria-pressed="($store.sidebar.layout === 'top').toString()">
                        <span class="sbp-lay l-top"><i></i></span>Top menu
                    </button>
                </div>
                <p class="sbp-note" style="margin-top:.4rem">Layouts apply on larger screens. Phones always use the slide-out menu.</p>

                <p class="sbp-label">Design</p>
                <div class="sbp-styles">
                    <button type="button" class="sbp-style" @click="$store.sidebar.set('style', 'classic')" :aria-pressed="($store.sidebar.style === 'classic').toString()">
                        <span class="sbp-mini m-classic"><b></b><b class="on"></b><b></b></span>Default
                    </button>
                    <button type="button" class="sbp-style" @click="$store.sidebar.set('style', 'light')" :aria-pressed="($store.sidebar.style === 'light').toString()">
                        <span class="sbp-mini m-light"><b></b><b class="on"></b><b></b></span>Light
                    </button>
                    <button type="button" class="sbp-style" @click="$store.sidebar.set('style', 'navy')" :aria-pressed="($store.sidebar.style === 'navy').toString()">
                        <span class="sbp-mini m-navy"><b></b><b class="on"></b><b></b></span>Navy
                    </button>
                    <button type="button" class="sbp-style" @click="$store.sidebar.set('style', 'pill')" :aria-pressed="($store.sidebar.style === 'pill').toString()">
                        <span class="sbp-mini m-pill"><b></b><b class="on"></b><b></b></span>Pill
                    </button>
                    <button type="button" class="sbp-style" @click="$store.sidebar.set('style', 'gradient')" :aria-pressed="($store.sidebar.style === 'gradient').toString()">
                        <span class="sbp-mini m-gradient"><b></b><b class="on"></b><b></b></span>Gradient
                    </button>
                    <button type="button" class="sbp-style" @click="$store.sidebar.set('style', 'glass')" :aria-pressed="($store.sidebar.style === 'glass').toString()">
                        <span class="sbp-mini m-glass"><b></b><b class="on"></b><b></b></span>Glass
                    </button>
                    <button type="button" class="sbp-style" @click="$store.sidebar.set('style', 'tab')" :aria-pressed="($store.sidebar.style === 'tab').toString()">
                        <span class="sbp-mini m-tab"><b></b><b class="on"></b><b></b></span>Tab
                    </button>
                </div>

                <p class="sbp-label">Sidebar colour</p>
                <div class="sbp-swatches" role="group" aria-label="Sidebar colour">
                    <button type="button" class="sbp-sw sbp-auto" title="Automatic (follows the design)" aria-label="Automatic" @click="$store.sidebar.set('bg', 'auto')" :aria-pressed="($store.sidebar.bg === 'auto').toString()"></button>
                    <button type="button" class="sbp-sw" title="White" aria-label="White" style="background:#ffffff" @click="$store.sidebar.set('bg', '#ffffff')" :aria-pressed="($store.sidebar.bg === '#ffffff').toString()"></button>
                    <button type="button" class="sbp-sw" title="Charcoal" aria-label="Charcoal" style="background:#0f172a" @click="$store.sidebar.set('bg', '#0f172a')" :aria-pressed="($store.sidebar.bg === '#0f172a').toString()"></button>
                    <button type="button" class="sbp-sw" title="Navy" aria-label="Navy" style="background:#12284a" @click="$store.sidebar.set('bg', '#12284a')" :aria-pressed="($store.sidebar.bg === '#12284a').toString()"></button>
                    <button type="button" class="sbp-sw" title="Teal" aria-label="Teal" style="background:#134e4a" @click="$store.sidebar.set('bg', '#134e4a')" :aria-pressed="($store.sidebar.bg === '#134e4a').toString()"></button>
                    <button type="button" class="sbp-sw" title="Plum" aria-label="Plum" style="background:#3b0764" @click="$store.sidebar.set('bg', '#3b0764')" :aria-pressed="($store.sidebar.bg === '#3b0764').toString()"></button>
                    <button type="button" class="sbp-sw" title="Maroon" aria-label="Maroon" style="background:#7f1d1d" @click="$store.sidebar.set('bg', '#7f1d1d')" :aria-pressed="($store.sidebar.bg === '#7f1d1d').toString()"></button>
                    <label class="sbp-sw sbp-pick" title="Pick any colour"><input type="color" :value="$store.sidebar.bg === 'auto' ? '#12284a' : $store.sidebar.bg" @input="$store.sidebar.set('bg', $event.target.value)" aria-label="Custom sidebar colour"></label>
                </div>

                <p class="sbp-label">Accent colour</p>
                <div class="sbp-swatches" role="group" aria-label="Accent colour">
                    <button type="button" class="sbp-sw sbp-auto" title="Automatic" aria-label="Automatic" @click="$store.sidebar.set('accent', 'auto')" :aria-pressed="($store.sidebar.accent === 'auto').toString()"></button>
                    <button type="button" class="sbp-sw" title="Blue" aria-label="Blue" style="background:#2563eb" @click="$store.sidebar.set('accent', '#2563eb')" :aria-pressed="($store.sidebar.accent === '#2563eb').toString()"></button>
                    <button type="button" class="sbp-sw" title="Sky" aria-label="Sky" style="background:#0ea5e9" @click="$store.sidebar.set('accent', '#0ea5e9')" :aria-pressed="($store.sidebar.accent === '#0ea5e9').toString()"></button>
                    <button type="button" class="sbp-sw" title="Violet" aria-label="Violet" style="background:#7c3aed" @click="$store.sidebar.set('accent', '#7c3aed')" :aria-pressed="($store.sidebar.accent === '#7c3aed').toString()"></button>
                    <button type="button" class="sbp-sw" title="Emerald" aria-label="Emerald" style="background:#059669" @click="$store.sidebar.set('accent', '#059669')" :aria-pressed="($store.sidebar.accent === '#059669').toString()"></button>
                    <button type="button" class="sbp-sw" title="Rose" aria-label="Rose" style="background:#e11d48" @click="$store.sidebar.set('accent', '#e11d48')" :aria-pressed="($store.sidebar.accent === '#e11d48').toString()"></button>
                    <button type="button" class="sbp-sw" title="Amber" aria-label="Amber" style="background:#f59e0b" @click="$store.sidebar.set('accent', '#f59e0b')" :aria-pressed="($store.sidebar.accent === '#f59e0b').toString()"></button>
                    <label class="sbp-sw sbp-pick" title="Pick any colour"><input type="color" :value="$store.sidebar.accent === 'auto' ? '#2563eb' : $store.sidebar.accent" @input="$store.sidebar.set('accent', $event.target.value)" aria-label="Custom accent colour"></label>
                </div>

                <p class="sbp-label">Spacing</p>
                <div class="sbp-seg" role="group" aria-label="Spacing">
                    <button type="button" @click="$store.sidebar.set('density', 'comfortable')" :aria-pressed="($store.sidebar.density === 'comfortable').toString()">Comfortable</button>
                    <button type="button" @click="$store.sidebar.set('density', 'compact')" :aria-pressed="($store.sidebar.density === 'compact').toString()">Compact</button>
                </div>

                <div class="sbp-row hidden lg:flex" x-show="$store.sidebar.layout === 'standard'">
                    <span id="sb-collapse-label">Icons only <span style="font-weight:400;color:#94a3b8">( [ )</span></span>
                    <button type="button" class="sbp-switch" role="switch" aria-labelledby="sb-collapse-label" @click="$store.sidebar.toggle()" :aria-checked="$store.sidebar.collapsed.toString()"></button>
                </div>

                <button type="button" class="sbp-reset" @click="$store.sidebar.reset()">Reset to default</button>
                <p class="sbp-note">Saved on this device for your account.</p>
            </div>
        </div>
    </aside>

    <div class="sb-main">
        {{-- Top bar --}}
        <header class="sticky top-0 z-20 flex h-14 items-center gap-3 border-b border-slate-200 bg-white px-4 sm:px-6">
            @if ($theme['accent'])
                <span class="pointer-events-none absolute inset-x-0 top-0 h-[3px] {{ $theme['accent'] }}" aria-hidden="true"></span>
            @endif
            <button type="button" @click="$store.sidebar.toggle()" class="sb-topbtn" :aria-label="$store.sidebar.collapsed ? 'Open sidebar' : 'Close sidebar'" :title="$store.sidebar.collapsed ? 'Open sidebar  ( [ )' : 'Close sidebar  ( [ )'">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="3"/><path d="M9 4v16"/></svg>
            </button>
            <button @click="open = true" class="rounded-lg p-2 text-slate-600 hover:bg-slate-100 lg:hidden" aria-label="Open menu">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
            <div class="text-lg font-semibold text-brand-800">{{ $header ?? '' }}</div>
            @if ($theme['icon'])
                <span class="hidden items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-bold uppercase tracking-wider md:inline-flex {{ $theme['headerPill'] }}">
                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $theme['icon'] !!}</svg>
                    {{ $theme['label'] }}
                </span>
            @endif
            <div class="ml-auto flex items-center gap-2 sm:gap-3">
                @include('layouts.partials.search-trigger')
                @include('layouts.partials.notification-bell')
                <span class="mx-1 hidden h-8 w-px bg-slate-200 sm:block" aria-hidden="true"></span>

                {{-- Account menu: the single place for identity, profile and log out --}}
                <div x-data="{ menu: false }" @keydown.escape.window="menu = false" class="relative">
                    <button type="button" @click="menu = !menu" :aria-expanded="menu" aria-haspopup="menu"
                            class="flex items-center gap-3 rounded-full py-1 pl-1 pr-2 transition hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 sm:pr-3"
                            :class="menu ? 'bg-slate-100' : ''" aria-label="Account menu">
                        <x-avatar :user="auth()->user()" :zoom="false" />
                        <span class="hidden text-left sm:block">
                            <span class="block max-w-[11rem] truncate text-sm font-semibold leading-tight text-slate-800">{{ auth()->user()->name }}</span>
                            <span class="block max-w-[11rem] truncate text-xs text-slate-500">{{ auth()->user()->company ?: auth()->user()->roleLabel() }}</span>
                        </span>
                        <svg class="hidden h-4 w-4 text-slate-400 transition-transform sm:block" :class="menu ? 'rotate-180' : ''" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.17l3.71-3.94a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd"/>
                        </svg>
                    </button>

                    <div x-show="menu" x-cloak @click.outside="menu = false" role="menu"
                         x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
                         x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                         class="absolute right-0 top-full z-50 mt-2 w-72 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl shadow-slate-900/10">
                        <div class="flex items-center gap-3 border-b border-slate-100 px-4 py-4">
                            <x-avatar :user="auth()->user()" size="h-12 w-12" text="text-base" />
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-slate-800">{{ auth()->user()->name }}</p>
                                <p class="truncate text-xs text-slate-500">{{ auth()->user()->email }}</p>
                                <span class="mt-1.5 inline-block rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $theme['pill'] }}">{{ auth()->user()->roleLabel() }}</span>
                            </div>
                        </div>
                        <div class="p-2">
                            <a href="{{ route('profile.edit') }}" role="menuitem"
                               class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-100">
                                <svg class="h-5 w-5 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $icons['profile'] !!}</svg>
                                My profile
                            </a>
                        </div>
                        <div class="border-t border-slate-100 p-2">
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" role="menuitem"
                                        class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-red-600 transition hover:bg-red-50">
                                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 12H3.5M7 8l-4 4 4 4M10 5V4.5A1.5 1.5 0 0 1 11.5 3h7A1.5 1.5 0 0 1 20 4.5v15a1.5 1.5 0 0 1-1.5 1.5h-7A1.5 1.5 0 0 1 10 19.5V19"/></svg>
                                    Log out
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <main id="main-content" class="mx-auto max-w-[96rem] p-4 sm:px-6 sm:py-5">
            @if (session('error'))
                <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">{{ session('error') }}</div>
            @endif
            @if (session('success'))
                <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
            @endif
            {{ $slot }}
        </main>
    </div>

    @include('layouts.partials.photo-viewer')
    @include('layouts.partials.command-palette')
</body>
</html>
