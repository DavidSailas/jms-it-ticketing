{{-- Super Admin only: accounts, sign-in security and a system-wide activity feed. --}}
@php
    $roleRows = [
        'super_admin' => ['Super Admins', 'bg-amber-400'],
        'admin'       => ['Admins',       'bg-violet-500'],
        'it_support'  => ['IT Engineers', 'bg-sky-500'],
        'user'        => ['Customers',     'bg-slate-400'],
    ];
    $accounts = max(1, $system['accounts']);
    $tiles = [
        ['Accounts', $system['accounts'], route('users.index'), 'text-brand-800', 'Across all roles', 'bg-brand-50 text-brand-700', '<path stroke-linecap="round" d="M16 19v-1a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v1"/><circle cx="10" cy="8" r="3"/><path stroke-linecap="round" d="M20 19v-1a3 3 0 0 0-2-2.8M16 5.2A3 3 0 0 1 16 11"/>'],
        ['Sign-ins (24h)', $system['logins'], '#system-activity', 'text-emerald-600', 'Successful', 'bg-emerald-50 text-emerald-600', '<path stroke-linecap="round" stroke-linejoin="round" d="M15 12H4m0 0 3-3m-3 3 3 3M10 5h7a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2h-7"/>'],
        ['Failed sign-ins (24h)', $system['failed'], '#system-activity', $system['failed'] > 0 ? 'text-red-600' : 'text-slate-700', $system['failed'] > 0 ? 'Worth a look' : 'All clear', $system['failed'] > 0 ? 'bg-red-50 text-red-600' : 'bg-slate-100 text-slate-500', '<path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.3 4.2 2.6 17.5A2 2 0 0 0 4.3 20.5h15.4a2 2 0 0 0 1.7-3L13.7 4.2a2 2 0 0 0-3.4 0Z"/>'],
        ['Security events (7d)', $system['security'], '#system-activity', 'text-amber-600', 'Password changes & resets', 'bg-amber-50 text-amber-600', '<path stroke-linecap="round" stroke-linejoin="round" d="M12 3 5 6v5c0 4.5 3 8 7 10 4-2 7-5.500 7-10V6l-7-3Z"/>'],
    ];
@endphp

<section class="mb-4" aria-labelledby="system-title">
    <div class="mb-2 flex items-center gap-2">
        <span class="flex h-6 w-6 items-center justify-center rounded-md bg-gradient-to-br from-amber-300 to-amber-500 text-brand-900">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! \App\Support\RoleTheme::CROWN !!}</svg>
        </span>
        <h3 id="system-title" class="text-sm font-bold uppercase tracking-wider text-brand-800">System overview</h3>
    </div>

    <div class="mb-3 grid grid-cols-2 gap-3 lg:grid-cols-4">
        @foreach ($tiles as [$label, $value, $href, $color, $hint, $iconTone, $iconPath])
            @php($tile = 'flex items-center gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm' . ($href ? ' transition hover:-translate-y-0.5 hover:shadow-md' : ''))
            @if ($href)
                <a href="{{ $href }}" class="{{ $tile }}">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg {{ $iconTone }}"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">{!! $iconPath !!}</svg></span>
                    <span class="min-w-0">
                        <span class="block text-xs font-medium text-slate-500">{{ $label }}</span>
                        <span class="block text-2xl font-bold leading-tight tracking-tight {{ $color }}">{{ number_format($value) }}</span>
                        <span class="block truncate text-xs text-slate-400">{{ $hint }}</span>
                    </span>
                </a>
            @else
                <div class="{{ $tile }}">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg {{ $iconTone }}"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">{!! $iconPath !!}</svg></span>
                    <span class="min-w-0">
                        <span class="block text-xs font-medium text-slate-500">{{ $label }}</span>
                        <span class="block text-2xl font-bold leading-tight tracking-tight {{ $color }}">{{ number_format($value) }}</span>
                        <span class="block truncate text-xs text-slate-400">{{ $hint }}</span>
                    </span>
                </div>
            @endif
        @endforeach
    </div>

    <div class="grid gap-3 xl:grid-cols-3">
        {{-- Accounts by role: stretches to the height of the activity feed, so there is no dead space under it --}}
        <div class="flex flex-col rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="mb-3 flex items-center justify-between">
                <h4 class="font-semibold text-brand-800">Accounts by role</h4>
                <a href="{{ route('users.index') }}" class="text-xs font-medium text-brand-600 hover:underline">Manage</a>
            </div>
            <div class="mb-3 flex h-2.5 overflow-hidden rounded-full bg-slate-100" role="img" aria-label="Share of accounts by role">
                @foreach ($roleRows as $key => [$label, $bar])
                    <span class="{{ $bar }}" style="width: {{ ($system['roles'][$key] ?? 0) / $accounts * 100 }}%" title="{{ $label }}"></span>
                @endforeach
            </div>
            <ul class="flex flex-1 flex-col justify-between">
                @foreach ($roleRows as $key => [$label, $bar])
                    @php($count = $system['roles'][$key] ?? 0)
                    <li>
                        <a href="{{ route('users.index', ['role' => $key]) }}" class="-mx-2 flex w-full flex-col justify-center rounded-lg px-2 py-2.5 transition hover:bg-slate-50">
                            <span class="flex items-center justify-between text-sm">
                                <span class="flex items-center gap-2.5 font-medium text-slate-700"><span class="h-2.5 w-2.5 rounded-full {{ $bar }}"></span>{{ $label }}</span>
                                <span class="flex items-center gap-2"><span class="font-semibold text-slate-900">{{ $count }}</span><span class="w-9 text-right text-xs text-slate-400">{{ round($count / $accounts * 100) }}%</span></span>
                            </span>
                            <span class="mt-1.5 hidden items-center gap-2" title="{{ round($count / $accounts * 100) }}% of all accounts">
                                <span class="h-1.5 flex-1 overflow-hidden rounded-full bg-slate-100"><span class="block h-full rounded-full {{ $bar }}" style="width: {{ $count / $accounts * 100 }}%"></span></span>
                                <span class="w-8 text-right text-[11px] text-slate-400">{{ round($count / $accounts * 100) }}%</span>
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>

        {{-- Recent activity across the system --}}
        <div id="system-activity" class="flex scroll-mt-24 flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm xl:col-span-2">
            <div class="border-b border-slate-100 px-4 py-3">
                <h4 class="font-semibold text-brand-800">Recent system activity</h4>
                <p class="text-xs text-slate-500">Sign-ins, security changes and ticket actions from everyone, newest first</p>
            </div>
            <ul class="max-h-[20rem] flex-1 divide-y divide-slate-100 overflow-y-auto">
                @forelse ($system['recent'] as $log)
                    @php([$class, $icon] = $log->look())
                    <li class="flex items-center gap-3 px-4 py-2.5">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full {{ $class }}">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $icon !!}</svg>
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium {{ $log->action === 'login_failed' ? 'text-red-700' : 'text-slate-800' }}">{{ $log->description }}</p>
                            <p class="truncate text-xs text-slate-500">{{ $log->user?->name ?? 'Unknown user' }}@if ($log->user) &middot; {{ $log->user->roleLabel() }}@endif @if ($log->ip_address) &middot; {{ $log->ip_address }}@endif</p>
                        </div>
                        <span class="shrink-0 text-xs text-slate-400" title="{{ $log->created_at->format('M d, Y h:i A') }}">{{ $log->created_at->diffForHumans(null, true, true) }}</span>
                    </li>
                @empty
                    <li class="px-4 py-6 text-center text-sm text-slate-500">No activity recorded yet.</li>
                @endforelse
            </ul>

            @if ($system['recent']->total() > 0)
                <div class="border-t border-slate-100 bg-slate-50/50 px-4 py-3">
                    @if ($system['recent']->hasPages())
                        {{ $system['recent']->links() }}
                    @else
                        <p class="text-sm text-slate-500">Showing all <span class="font-semibold text-slate-700">{{ $system['recent']->total() }}</span> {{ \Illuminate\Support\Str::plural('entry', $system['recent']->total()) }}</p>
                    @endif
                </div>
            @endif
        </div>
    </div>
</section>
