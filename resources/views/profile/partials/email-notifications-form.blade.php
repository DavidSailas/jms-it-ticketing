@php
    $on = (bool) ($user->email_notifications ?? true);
    $events = $user->isStaff()
        ? ['A new ticket is raised', 'A ticket is assigned to you', 'A partner replies on your ticket', 'A critical ticket needs attention']
        : ['An engineer is assigned to your ticket', 'We reply to your ticket', 'Your ticket is resolved', 'Your visit or session is rescheduled'];
@endphp

<section x-data="{ on: @js($on) }">
    <header>
        <h2 class="text-lg font-semibold text-brand-800">Email notifications</h2>
        <p class="mt-1 text-sm text-slate-500">Get an email at <span class="font-medium text-slate-700">{{ $user->email }}</span> so you don't have to keep the app open. The bell inside the app always works.</p>
    </header>

    @if (session('status') === 'notifications-updated')
        <x-form-alert type="success" title="Saved" class="mt-5">Your email preference was updated.</x-form-alert>
    @endif

    <form method="POST" action="{{ route('profile.notifications') }}" class="mt-5 space-y-5">
        @csrf
        @method('PATCH')
        <input type="hidden" name="email_notifications" :value="on ? 1 : 0">

        <div class="flex items-start justify-between gap-4 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3.5">
            <div class="min-w-0">
                <p id="email-pref-label" class="text-sm font-semibold text-slate-800">Email me about ticket activity</p>
                <p class="mt-0.5 text-xs text-slate-500" x-text="on ? 'On. You will get an email for the events below.' : 'Off. You will only see updates in the app.'"></p>
            </div>
            <button type="button" role="switch" :aria-checked="on" aria-labelledby="email-pref-label" @click="on = ! on"
                    :class="on ? 'bg-brand-600' : 'bg-slate-300'"
                    class="relative mt-0.5 inline-flex h-6 w-11 shrink-0 items-center rounded-full transition focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2">
                <span :class="on ? 'translate-x-6' : 'translate-x-1'" class="inline-block h-4 w-4 rounded-full bg-white shadow transition"></span>
            </button>
        </div>

        <div>
            <p class="text-xs font-semibold text-slate-500">You will be emailed when</p>
            <ul class="mt-2 space-y-1.5 text-sm text-slate-600">
                @foreach ($events as $event)
                    <li class="flex items-center gap-2">
                        <svg class="h-4 w-4 shrink-0 text-emerald-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>
                        {{ $event }}
                    </li>
                @endforeach
            </ul>
            <p class="mt-3 text-xs text-slate-400">Security emails, like a password reset, are always sent.</p>
        </div>

        <x-primary-button>Save preference</x-primary-button>
    </form>
</section>
