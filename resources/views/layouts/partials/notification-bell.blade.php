@php
    $kinds = collect(\App\Support\NotificationKinds::all())
        ->map(fn ($v) => ['class' => $v[0], 'icon' => $v[1]])->all();

    $config = [
        'initial'   => auth()->user()->notificationFeed(8),
        'kinds'     => $kinds,
        'feedUrl'   => route('notifications.feed'),
        'readAllUrl' => route('notifications.read-all'),
        'csrf'      => csrf_token(),
        'pollMs'    => 20000,
    ];
@endphp

<div x-data="notificationBell(@js($config))" @keydown.escape.window="open = false" @pending-new.window="poll()" @live-notification.window="poll()" class="relative">

    {{-- Bell button --}}
    <button type="button" @click="open = !open" :aria-expanded="open"
            :aria-label="unread ? 'Notifications, ' + unread + ' unread' : 'Notifications'"
            class="relative flex h-10 w-10 items-center justify-center rounded-full text-slate-500 transition hover:bg-slate-100 hover:text-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500">
        <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0"/>
        </svg>
        <span x-show="unread > 0" x-cloak x-text="badge()"
              class="absolute -right-0.5 -top-0.5 flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-red-500 px-1 text-[11px] font-bold leading-none text-white ring-2 ring-white"></span>
    </button>

    {{-- Dropdown panel --}}
    <div x-show="open" x-cloak @click.outside="open = false"
         x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         class="fixed inset-x-3 top-[4.25rem] z-50 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl shadow-slate-900/10 sm:absolute sm:inset-x-auto sm:right-0 sm:top-full sm:mt-2 sm:w-[24rem]"
         role="dialog" aria-label="Notifications">

        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
            <div class="flex items-center gap-2">
                <h3 class="font-semibold text-brand-800">Notifications</h3>
                <span x-show="unread > 0" x-cloak class="rounded-full bg-red-50 px-2 py-0.5 text-xs font-semibold text-red-600" x-text="unread + ' new'"></span>
            </div>
            <button type="button" @click="markAll()" x-show="unread > 0" x-cloak class="text-xs font-semibold text-brand-600 hover:text-brand-800 hover:underline">Mark all as read</button>
        </div>

        <div class="max-h-[26rem] overflow-y-auto">
            <template x-for="item in items" :key="item.id">
                <a :href="item.url" class="flex gap-3 border-b border-slate-50 px-4 py-3 transition hover:bg-slate-50" :class="item.read ? '' : 'bg-brand-50/50'">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full" :class="look(item.kind).class">
                        <svg class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" x-html="look(item.kind).icon" aria-hidden="true"></svg>
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="flex items-start justify-between gap-2">
                            <span class="text-sm leading-snug text-slate-800" :class="item.read ? 'font-medium' : 'font-semibold'" x-text="item.title"></span>
                            <span x-show="!item.read" class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-brand-500" aria-label="Unread"></span>
                        </span>
                        <span class="mt-0.5 line-clamp-2 block text-xs leading-snug text-slate-500" x-text="item.message"></span>
                        <span class="mt-1 block text-[11px] text-slate-400" x-text="item.time"></span>
                    </span>
                </a>
            </template>

            <div x-show="items.length === 0" class="px-6 py-12 text-center">
                <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-slate-400">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><path d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0"/></svg>
                </span>
                <p class="mt-3 text-sm font-semibold text-slate-700">You're all caught up</p>
                <p class="mt-1 text-xs text-slate-500">New activity on your tickets will appear here.</p>
            </div>
        </div>

        <a href="{{ route('notifications.index') }}" class="block border-t border-slate-100 bg-slate-50 px-4 py-3 text-center text-sm font-semibold text-brand-600 transition hover:bg-slate-100">View all notifications</a>
    </div>

    {{-- Live pop-up toasts --}}
    <div class="pointer-events-none fixed right-4 top-20 z-[60] flex w-[calc(100vw-2rem)] max-w-sm flex-col gap-3" aria-live="polite">
        <template x-for="t in toasts" :key="t.id">
            <div x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-x-6" x-transition:enter-end="opacity-100 translate-x-0"
                 x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                 class="pointer-events-auto flex gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-xl shadow-slate-900/10" role="status">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full" :class="look(t.kind).class">
                    <svg class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" x-html="look(t.kind).icon" aria-hidden="true"></svg>
                </span>
                <a :href="t.url" class="min-w-0 flex-1">
                    <span class="block text-sm font-semibold leading-snug text-slate-800" x-text="t.title"></span>
                    <span class="mt-0.5 line-clamp-2 block text-xs text-slate-500" x-text="t.message"></span>
                    <span class="mt-1.5 block text-xs font-semibold text-brand-600">View ticket &rarr;</span>
                </a>
                <button type="button" @click="dismiss(t.id)" class="-mr-1 -mt-1 h-6 w-6 shrink-0 rounded-full text-slate-400 hover:bg-slate-100 hover:text-slate-600" aria-label="Dismiss">
                    <svg class="mx-auto h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
                </button>
            </div>
        </template>
    </div>
</div>
