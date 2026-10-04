<x-app-layout>
    <x-slot name="header">Notifications</x-slot>

    @php($kinds = \App\Support\NotificationKinds::all())

    <div class="mx-auto max-w-3xl">
        <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex gap-1">
                @foreach (['all' => 'All', 'unread' => 'Unread (' . $unread . ')'] as $key => $label)
                    <a href="{{ route('notifications.index', $key === 'all' ? [] : ['filter' => $key]) }}"
                       class="rounded-full px-4 py-2 text-sm font-medium transition {{ $filter === $key ? 'bg-brand-800 text-white shadow-sm' : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50' }}">{{ $label }}</a>
                @endforeach
            </div>
            @if ($unread > 0)
                <form method="POST" action="{{ route('notifications.read-all') }}">
                    @csrf
                    <button class="text-sm font-semibold text-brand-600 hover:text-brand-800 hover:underline">Mark all as read</button>
                </form>
            @endif
        </div>

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            @forelse ($items as $n)
                @php([$class, $icon] = $kinds[$n['kind']] ?? $kinds['default'])
                <a href="{{ $n['url'] }}" class="flex gap-4 border-b border-slate-100 px-5 py-4 transition last:border-0 hover:bg-slate-50 {{ $n['read'] ? '' : 'bg-brand-50/50' }}">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full {{ $class }}">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">{!! $icon !!}</svg>
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="flex items-start justify-between gap-3">
                            <span class="text-sm text-slate-800 {{ $n['read'] ? 'font-medium' : 'font-semibold' }}">{{ $n['title'] }}</span>
                            <span class="shrink-0 text-xs text-slate-400">{{ $n['time'] }}</span>
                        </span>
                        <span class="mt-0.5 block text-sm text-slate-500">{{ $n['message'] }}</span>
                    </span>
                    @unless ($n['read']) <span class="mt-2 h-2 w-2 shrink-0 rounded-full bg-brand-500" aria-label="Unread"></span> @endunless
                </a>
            @empty
                <div class="px-6 py-16 text-center">
                    <p class="font-semibold text-slate-700">{{ $filter === 'unread' ? 'No unread notifications' : "You're all caught up" }}</p>
                    <p class="mt-1 text-sm text-slate-500">New activity on your tickets will appear here.</p>
                </div>
            @endforelse
        </div>

        <div class="mt-4">{{ $items->links() }}</div>
    </div>
</x-app-layout>
