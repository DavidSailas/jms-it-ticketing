<x-app-layout>
    <x-slot name="header">My Profile</x-slot>

    @php
        // Open the tab the person was just working in (e.g. password errors, a paged log).
        $tab = request()->has('logs_page') || request()->has('type') ? 'activity'
            : (($errors->updatePassword->any() || session('status') === 'password-updated') ? 'security' : 'profile');

        $tabs = [
            'profile'  => ['Profile',      '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>'],
            'security' => ['Security',     '<rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/>'],
            'activity' => ['Activity log', '<path d="M3 12h4l3-8 4 16 3-8h4"/>'],
        ];
    @endphp

    <div class="mx-auto max-w-4xl space-y-6" x-data="{ tab: '{{ $tab }}' }">

        {{-- Who you are, at a glance --}}
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex min-w-0 items-center gap-4">
                    <div class="min-w-0">
                        <h2 class="truncate text-xl font-bold text-brand-800">{{ $user->name }}</h2>
                        <p class="truncate text-sm text-slate-500">{{ $user->email }}</p>
                        <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1">
                            <span class="rounded-full bg-brand-50 px-2.5 py-0.5 text-xs font-semibold text-brand-700">{{ $user->roleLabel() }}</span>
                            @if ($user->company) <span class="text-xs text-slate-500">{{ $user->company }}</span> @endif
                            <span class="text-xs text-slate-400">Member since {{ $user->created_at?->format('M Y') ?? '-' }}</span>
                        </div>
                    </div>
                </div>

                <dl class="grid shrink-0 grid-cols-3 gap-3 lg:w-80">
                    @foreach ($stats as [$label, $value, $color])
                        <div class="rounded-xl bg-slate-50 px-3 py-3 text-center">
                            <dd class="text-xl font-bold {{ $color }}">{{ $value }}</dd>
                            <dt class="mt-0.5 text-xs text-slate-500">{{ $label }}</dt>
                        </div>
                    @endforeach
                </dl>
            </div>
        </section>

        {{-- Tabs --}}
        <div id="profile-tabs" class="scroll-mt-20 border-b border-slate-200">
            <nav class="-mb-px flex gap-5 overflow-x-auto" role="tablist" aria-label="Profile sections">
                @foreach ($tabs as $key => [$label, $icon])
                    <button type="button" role="tab" @click="tab = '{{ $key }}'" :aria-selected="tab === '{{ $key }}'"
                            :class="tab === '{{ $key }}' ? 'border-brand-600 text-brand-700' : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700'"
                            class="flex shrink-0 items-center gap-2 border-b-2 px-1 pb-3 pt-1 text-sm font-semibold transition focus:outline-none focus-visible:text-brand-700">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $icon !!}</svg>
                        {{ $label }}
                    </button>
                @endforeach
            </nav>
        </div>

        {{-- Profile --}}
        <div x-show="tab === 'profile'" @if ($tab !== 'profile') x-cloak @endif role="tabpanel" class="space-y-6">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-8">
                @include('profile.partials.update-avatar-form')
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-8">
                <div class="max-w-xl">
                    @include('profile.partials.update-profile-information-form')
                </div>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-8">
                <div class="max-w-xl">
                    @include('profile.partials.email-notifications-form')
                </div>
            </div>
        </div>

        {{-- Security --}}
        <div x-show="tab === 'security'" @if ($tab !== 'security') x-cloak @endif role="tabpanel">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-8">
                <div class="max-w-xl">
                    @include('profile.partials.update-password-form')
                </div>
            </div>
        </div>

        {{-- Activity log --}}
        <div x-show="tab === 'activity'" @if ($tab !== 'activity') x-cloak @endif role="tabpanel">
            @include('profile.partials.activity-log')
        </div>
    </div>
</x-app-layout>
