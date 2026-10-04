@php
    $cats  = ['all' => 'All'] + \App\Support\ActivityKinds::CATEGORIES;
    $total = $logCounts->sum();
@endphp

<section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <header class="border-b border-slate-100 p-5 sm:p-6">
        <h2 class="text-lg font-semibold text-brand-800">Activity log</h2>
        <p class="mt-1 text-sm text-slate-500">Your recent sign-ins, security changes and ticket actions, newest first. If you see something you don't recognize, change your password and tell your IT administrator.</p>

        <div class="-mx-1 mt-4 flex gap-1 overflow-x-auto px-1 pb-1">
            @foreach ($cats as $key => $label)
                @php
                    $active = $key === 'all' ? $logType === null : $logType === $key;
                    $count  = $key === 'all' ? $total : ($logCounts[$key] ?? 0);
                @endphp
                <a href="{{ route('profile.edit', ['type' => $key]) }}#profile-tabs"
                   @if ($active) aria-current="true" @endif
                   class="flex shrink-0 items-center gap-2 rounded-full px-4 py-2 text-sm font-medium transition {{ $active ? 'bg-brand-800 text-white shadow-sm' : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50' }}">
                    {{ $label }}
                    <span class="rounded-full px-1.5 text-xs {{ $active ? 'bg-white/20' : 'bg-slate-100 text-slate-500' }}">{{ $count }}</span>
                </a>
            @endforeach
        </div>
    </header>

    <ul class="divide-y divide-slate-100">
        @forelse ($logs as $log)
            @php([$class, $icon] = $log->look())
            <li class="flex items-start gap-4 px-5 py-4 transition hover:bg-slate-50/70 sm:px-6">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full {{ $class }}">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $icon !!}</svg>
                </span>

                <div class="min-w-0 flex-1 sm:flex sm:items-start sm:justify-between sm:gap-6">
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-slate-800 {{ $log->action === 'login_failed' ? 'text-red-700' : '' }}">{{ $log->description }}</p>
                        <p class="mt-1 flex flex-wrap items-center gap-x-4 gap-y-0.5 text-xs text-slate-500">
                            @if ($log->device()) <span>{{ $log->device() }}</span> @endif
                            @if ($log->ip_address) <span>IP {{ $log->ip_address }}</span> @endif
                            @if ($log->ticket_id)
                                <a href="{{ route('tickets.show', $log->ticket_id) }}" class="font-semibold text-brand-600 hover:text-brand-800 hover:underline">View ticket</a>
                            @endif
                        </p>
                    </div>
                    <div class="mt-1 shrink-0 sm:mt-0 sm:text-right">
                        <p class="text-xs font-medium text-slate-600" title="{{ $log->created_at->format('l, M d, Y \a\t h:i:s A') }}">{{ $log->whenLabel() }}</p>
                        <p class="text-xs text-slate-400">{{ $log->created_at->diffForHumans() }}</p>
                    </div>
                </div>
            </li>
        @empty
            <li class="px-6 py-16 text-center">
                <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-slate-400">
                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 12h4l3-8 4 16 3-8h4"/></svg>
                </span>
                <p class="mt-3 text-sm font-semibold text-slate-700">{{ $logType ? 'No ' . strtolower($cats[$logType]) . ' activity yet' : 'No activity yet' }}</p>
                <p class="mt-1 text-sm text-slate-500">{{ $logType ? 'Try another filter to see the rest of your activity.' : 'Sign-ins, security changes and ticket actions will appear here.' }}</p>
            </li>
        @endforelse
    </ul>

    @if ($logs->total() > 0)
        <footer class="border-t border-slate-100 bg-slate-50/50 px-5 py-4 sm:px-6">
            @if ($logs->hasPages())
                {{ $logs->links() }}
            @else
                <p class="text-sm text-slate-500">Showing all <span class="font-semibold text-slate-700">{{ $logs->total() }}</span> {{ \Illuminate\Support\Str::plural('entry', $logs->total()) }}</p>
            @endif
        </footer>
    @endif
</section>
