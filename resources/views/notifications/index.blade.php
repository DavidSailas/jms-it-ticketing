<x-app-layout>
    <x-slot name="header">Notifications</x-slot>

    @php
        $kinds = \App\Support\NotificationKinds::all();

        // Group the rows of this page by day, so a long list is easy to scan.
        $dayLabel = function (int $ts): string {
            $d = \Illuminate\Support\Carbon::createFromTimestamp($ts);
            return match (true) {
                $d->isToday()                           => 'Today',
                $d->isYesterday()                       => 'Yesterday',
                $d->greaterThanOrEqualTo(now()->subDays(6)->startOfDay()) => 'Earlier this week',
                $d->isSameMonth(now())                  => 'Earlier this month',
                default                                 => $d->format('F Y'),
            };
        };
        $groups = collect($items->items())->groupBy(fn ($n) => $dayLabel($n['ts']));
    @endphp

    <div class="mx-auto max-w-3xl">
        <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex gap-1" role="tablist" aria-label="Filter notifications">
                @foreach (['all' => 'All', 'unread' => 'Unread (' . $unread . ')'] as $key => $label)
                    <a href="{{ route('notifications.index', $key === 'all' ? [] : ['filter' => $key]) }}" @if ($filter === $key) aria-current="page" @endif
                       class="rounded-full px-4 py-2 text-sm font-medium transition {{ $filter === $key ? 'bg-brand-800 text-white shadow-sm' : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50' }}">{{ $label }}</a>
                @endforeach
            </div>
            @if ($unread > 0)
                <form method="POST" action="{{ route('notifications.read-all') }}">
                    @csrf
                    <button class="inline-flex items-center gap-1.5 text-sm font-semibold text-brand-600 hover:text-brand-800 hover:underline">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m3 12 5 5L18 7M10 17l1 1L21 8"/></svg>
                        Mark all as read
                    </button>
                </form>
            @endif
        </div>

        @if ($items->total() > 0)
            <p class="mb-2 px-1 text-xs text-slate-500">
                Showing <span class="font-semibold text-slate-700">{{ $items->firstItem() }}&ndash;{{ $items->lastItem() }}</span>
                of <span class="font-semibold text-slate-700">{{ number_format($items->total()) }}</span>
                {{ $filter === 'unread' ? 'unread' : '' }} {{ \Illuminate\Support\Str::plural('notification', $items->total()) }}
            </p>
        @endif

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            @forelse ($groups as $label => $rows)
                <h2 class="border-b border-slate-100 bg-slate-50 px-5 py-2 text-[11px] font-semibold uppercase tracking-wider text-slate-500 {{ $loop->first ? '' : 'border-t' }}">{{ $label }}</h2>

                @foreach ($rows as $n)
                    @php([$class, $icon] = $kinds[$n['kind']] ?? $kinds['default'])
                    <a href="{{ $n['url'] }}"
                       class="flex gap-4 border-b border-l-[3px] border-slate-100 px-5 py-4 transition last:border-b-0 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-brand-500
                              {{ $n['read'] ? 'border-l-transparent' : 'border-l-brand-500 bg-brand-50/50' }} {{ $n['unavailable'] ? 'opacity-60' : '' }}">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full {{ $class }}">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $icon !!}</svg>
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="flex items-start justify-between gap-3">
                                <span class="text-sm text-slate-800 {{ $n['read'] ? 'font-medium' : 'font-semibold' }}">{{ $n['title'] }}</span>
                                <span class="shrink-0 text-xs text-slate-400">{{ $n['time'] }}</span>
                            </span>
                            <span class="mt-0.5 block break-words text-sm text-slate-500">{{ $n['message'] }}</span>
                            @if ($n['unavailable'])
                                <span class="mt-1.5 inline-block rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-500">No longer available to you</span>
                            @endif
                        </span>
                        @unless ($n['read']) <span class="mt-2 h-2 w-2 shrink-0 rounded-full bg-brand-500" aria-label="Unread"></span> @endunless
                    </a>
                @endforeach
            @empty
                <div class="px-6 py-16 text-center">
                    <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-emerald-50 text-emerald-600">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12 5 5L20 7"/></svg>
                    </span>
                    <p class="mt-3 font-semibold text-slate-700">{{ $filter === 'unread' ? 'No unread notifications' : "You're all caught up" }}</p>
                    <p class="mt-1 text-sm text-slate-500">
                        {{ $filter === 'unread' ? 'Everything has been read.' : 'New activity on your tickets will appear here.' }}
                        @if ($filter === 'unread') <a href="{{ route('notifications.index') }}" class="font-semibold text-brand-600 hover:underline">See all notifications</a> @endif
                    </p>
                </div>
            @endforelse
        </div>

        <div class="mt-4">{{ $items->links() }}</div>
    </div>
</x-app-layout>
