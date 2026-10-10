@php
    $role = auth()->user()->role;
    $theme = \App\Support\RoleTheme::for($role);
    $isUser = $role === 'user';
    // JMS's own admins have no partner company: they dispatch tickets but do not log them or brand a company.
    $jmsNoCompany = auth()->user()->isJmsAdmin() && ! auth()->user()->company_id;
    // Each company can have its own name, logo and colour; JMS staff and companies without a logo see the standard JMS logo.
    $company   = auth()->user()->companyRecord;
    $brandLogo = $company?->logoUrl() ?? asset('images/logo.png');
    $brandName = $company?->name ?? 'JMS One IT';
    $brandCss  = \App\Support\Branding::css($company?->brand_color);
    $all = ['user', 'it_support', 'admin', 'super_admin'];
    $staff = ['it_support', 'admin', 'super_admin'];
    // Admins get the live "Waiting for acceptance" queue (shared by the sidebar, dashboard and ticket list).
    $pendingFeed = in_array($role, ['admin', 'super_admin']) ? \App\Support\PendingTickets::feed() : null;
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
        'chart'   => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
        'book'    => '<path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v15H6.5A2.5 2.5 0 0 0 4 20.5v-15zM4 20.5A2.5 2.5 0 0 0 6.5 23H20v-5"/>',
        'chat'    => '<path d="M21 12a8 8 0 0 1-11.6 7.1L3 20.5l1.5-4.6A8 8 0 1 1 21 12z"/><path d="M8.5 11h7M8.5 14h4"/>',
        'profile' => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="10" r="3"/><path d="M6.5 18.5a6 6 0 0 1 11 0"/>',
    ];
    // [label, route, active-patterns, roles, icon, group]
    $nav = [
        [$isUser ? 'New Request' : 'Dashboard', 'dashboard', $isUser ? 'dashboard|tickets.create' : 'dashboard', $all, $isUser ? 'plus' : 'grid', 'main'],
        [$isUser ? 'My Tickets' : ($role === 'it_support' ? 'My Assignments' : 'All Tickets'), 'tickets.index', 'tickets.index|tickets.show', $all, 'list', 'main'],
        ['Schedule', 'schedule.index', 'schedule.*', $staff, 'calendar', 'main'],
        ['Saved Replies', 'canned-replies.index', 'canned-replies.*', $staff, 'chat', 'main'],
        ['New Ticket', 'tickets.create', 'tickets.create', ['admin'], 'plus', 'main'],
        ['Companies', 'companies.index', 'companies.*', ['super_admin'], 'building', 'manage'],
        ['Users', 'users.index', 'users.*', ['admin', 'super_admin'], 'users', 'manage'],
        ['Branding', 'branding.edit', 'branding.*', ['admin'], 'palette', 'manage'],
        ['Reports', 'reports.index', 'reports.*', ['super_admin'], 'chart', 'manage'],
        ['Workflow Guide', 'workflow.index', 'workflow.*', ['super_admin'], 'book', 'manage'],
    ];

    // Ctrl+K search: "Go to" shortcuts for this role (the same pages as the sidebar, plus a few quick filters) and the
    // "keep looking" links that open the full lists with the search already typed in.
    $searchPages = collect($nav)
        ->filter(fn ($n) => in_array($role, $n[3]) && ! ($jmsNoCompany && in_array($n[1], ['tickets.create', 'branding.edit'])))
        ->map(fn ($n) => ['title' => $n[0], 'url' => route($n[1], absolute: false), 'icon' => $icons[$n[4]] ?? ''])
        ->concat([
            ['title' => 'Open tickets', 'url' => route('tickets.index', ['status' => 'open'], false), 'icon' => $icons['list']],
            ['title' => 'In progress', 'url' => route('tickets.index', ['status' => 'in_progress'], false), 'icon' => $icons['list']],
            ['title' => 'Resolved tickets', 'url' => route('tickets.index', ['status' => 'resolved'], false), 'icon' => $icons['list']],
            ['title' => 'Notifications', 'url' => route('notifications.index', absolute: false), 'icon' => $icons['list']],
            ['title' => 'My profile', 'url' => route('profile.edit', absolute: false), 'icon' => $icons['profile']],
        ])
        ->when(in_array($role, ['admin', 'super_admin']), fn ($c) => $c->push(
            ['title' => 'Waiting for acceptance', 'url' => route('tickets.index', ['status' => 'unassigned'], false), 'icon' => $icons['list']]
        ))
        ->unique('url')->values()->all();
    $searchMore = array_values(array_filter([
        ['title' => 'Search all tickets for "{q}"', 'url' => route('tickets.index', absolute: false) . '?search='],
        in_array($role, ['admin', 'super_admin']) ? ['title' => 'Search users for "{q}"', 'url' => route('users.index', absolute: false) . '?search='] : null,
    ]));
    $searchConfig = ['url' => route('search'), 'pages' => $searchPages, 'more' => $searchMore];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
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
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @if ($brandCss)
        <style>{!! $brandCss !!}</style>
    @endif
</head>
<body class="font-sans antialiased bg-slate-50 text-slate-800" x-data="{ open: false }">

    <a href="#main-content" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-lg focus:bg-white focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-brand-800 focus:shadow-lg">Skip to content</a>

    @if ($pendingFeed)
        {{-- Starts the live queue first, so everything below renders with real numbers. --}}
        <div x-data x-init="$store.pending.start(@js($pendingConfig))" hidden></div>
    @endif

    {{-- Mobile overlay --}}
    <div x-show="open" x-cloak @click="open = false" class="fixed inset-0 z-30 bg-slate-900/50 lg:hidden"></div>

    {{-- Sidebar: its look follows the signed-in role (see App\Support\RoleTheme) --}}
    <aside :class="open ? 'translate-x-0' : '-translate-x-full'"
           class="fixed inset-y-0 left-0 z-40 flex w-64 flex-col border-r {{ $theme['aside'] }} transition-transform lg:translate-x-0">
        <div class="border-b {{ $theme['divider'] }} {{ $theme['icon'] ? 'px-4 pb-4 pt-5' : 'p-4' }}">
            <div class="flex justify-center">
                @if ($company?->logo_path)
                    {{-- A company's own logo, shown as is: the upload is already trimmed and has a transparent background. --}}
                    <img src="{{ $brandLogo }}" alt="{{ $brandName }}" class="max-h-20 w-auto max-w-[11rem] object-contain">
                @else
                    <div class="{{ $theme['logoCard'] }}"><img src="{{ $brandLogo }}" alt="{{ $brandName }}" class="{{ $theme['icon'] ? 'h-20 w-20' : 'h-24' }} max-w-[12rem] object-contain"></div>
                @endif
            </div>
            @if ($company)
                <p class="mt-2 truncate text-center text-sm font-bold text-brand-800">{{ $company->name }}</p>
            @endif
            @if ($theme['icon'])
                <div class="mt-3 flex justify-center">
                    <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-[11px] font-bold uppercase tracking-wider {{ $theme['tag'] }}">
                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $theme['icon'] !!}</svg>
                        {{ $theme['label'] }} Console
                    </span>
                </div>
            @endif
        </div>

        <nav class="flex-1 space-y-1 overflow-y-auto p-4" aria-label="Main">
            @php($lastGroup = null)
            @foreach ($nav as [$label, $route, $pattern, $roles, $icon, $group])
                @if (in_array($role, $roles) && ! ($jmsNoCompany && in_array($route, ['tickets.create', 'branding.edit'])))
                    @if ($theme['headings'] && $group !== $lastGroup)
                        <p class="px-3 pb-1 pt-4 text-[11px] font-semibold uppercase tracking-wider first:pt-0 {{ $theme['heading'] }}">{{ $theme['groups'][$group] ?? '' }}</p>
                        @php($lastGroup = $group)
                    @endif
                    @php($active = request()->routeIs(explode('|', $pattern)))
                    <a href="{{ route($route) }}" @if ($active) aria-current="page" @endif
                       class="flex items-center gap-3 rounded-lg border-l-4 px-3 py-2.5 text-sm font-medium transition {{ $active ? $theme['navActive'] : $theme['navIdle'] }}">
                        <svg class="h-5 w-5 shrink-0 {{ $active ? $theme['iconActive'] : $theme['iconIdle'] }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">{!! $icons[$icon] !!}</svg>
                        {{ $label }}
                        @if ($route === 'tickets.index' && $pendingFeed)
                            <span x-data x-show="$store.pending.count > 0" x-cloak
                                  x-text="$store.pending.count > 99 ? '99+' : $store.pending.count"
                                  class="ml-auto rounded-full bg-violet-600 px-2 py-0.5 text-xs font-semibold text-white"
                                  title="Waiting for acceptance" aria-label="Tickets waiting for acceptance"></span>
                        @endif
                        @if ($route === 'schedule.index' && $todayBookings > 0)
                            <span class="ml-auto rounded-full px-2 py-0.5 text-xs font-semibold {{ $theme['count'] }}"
                                  title="Booked for today" aria-label="{{ $todayBookings }} booked for today">{{ $todayBookings > 99 ? '99+' : $todayBookings }}</span>
                        @endif
                    </a>
                @endif
            @endforeach
        </nav>

        @if ($theme['icon'])
            <div class="border-t {{ $theme['divider'] }} p-4">
                <div class="flex items-center gap-3 rounded-xl p-3 {{ $theme['footerCard'] }}">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg {{ $theme['footerIcon'] }}">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $theme['icon'] !!}</svg>
                    </span>
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold {{ $theme['footerTitle'] }}">{{ $theme['label'] }}</p>
                        <p class="text-xs leading-snug {{ $theme['footerText'] }}">{{ $theme['tagline'] }}</p>
                    </div>
                </div>
            </div>
        @endif
    </aside>

    <div class="lg:pl-64">
        {{-- Top bar --}}
        <header class="sticky top-0 z-20 flex h-14 items-center gap-3 border-b border-slate-200 bg-white px-4 sm:px-6">
            @if ($theme['accent'])
                <span class="pointer-events-none absolute inset-x-0 top-0 h-[3px] {{ $theme['accent'] }}" aria-hidden="true"></span>
            @endif
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
                {{-- Admins can log a ticket from anywhere --}}
                @if (auth()->user()->canLogTickets() && $role === 'admin' && ! request()->routeIs('tickets.create', 'tickets.index'))
                    <a href="{{ route('tickets.create') }}"
                       class="hidden items-center gap-1.5 rounded-lg bg-brand-800 px-3.5 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2 sm:inline-flex">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
                        New ticket
                    </a>
                @endif
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
