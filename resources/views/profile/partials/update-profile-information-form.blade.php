@php
    $input = 'mt-1.5 block w-full rounded-lg text-sm shadow-sm focus:ring-1';
    $ok    = 'border-slate-300 focus:border-brand-500 focus:ring-brand-500';
    $bad   = 'border-red-400 focus:border-red-500 focus:ring-red-500';
    $fixed = 'mt-1.5 block w-full cursor-not-allowed rounded-lg border-slate-200 bg-slate-50 text-sm text-slate-500 shadow-none';
@endphp

<section>
    <header>
        <h2 class="text-lg font-semibold text-brand-800">Personal information</h2>
        <p class="mt-1 text-sm text-slate-500">Keep your name and email current so we can reach you about your tickets.</p>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    @if (session('status') === 'profile-updated')
        <x-form-alert type="success" class="mt-5" x-data="{ show: true }" x-show="show" x-transition.opacity x-init="setTimeout(() => show = false, 5000)">
            Your changes were saved.
        </x-form-alert>
    @endif

    <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-5">
        @csrf
        @method('patch')

        <div>
            <label for="name" class="block text-sm font-medium text-slate-700">Full name</label>
            <input id="name" name="name" type="text" value="{{ old('name', $user->name) }}" required autocomplete="name"
                   @error('name') aria-invalid="true" aria-describedby="name-error" @enderror
                   class="{{ $input }} {{ $errors->has('name') ? $bad : $ok }}">
            @error('name')
                <p id="name-error" class="mt-1.5 flex items-start gap-1.5 text-sm text-red-600">
                    <svg class="mt-0.5 h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v4.5M12 16h.01"/></svg>{{ $message }}
                </p>
            @enderror
        </div>

        <div>
            <label for="email" class="block text-sm font-medium text-slate-700">Email address</label>
            <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required autocomplete="username"
                   @error('email') aria-invalid="true" aria-describedby="email-error" @enderror
                   class="{{ $input }} {{ $errors->has('email') ? $bad : $ok }}">
            @error('email')
                <p id="email-error" class="mt-1.5 flex items-start gap-1.5 text-sm text-red-600">
                    <svg class="mt-0.5 h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v4.5M12 16h.01"/></svg>{{ $message }}
                </p>
            @enderror

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div>
                    <p class="mt-2 text-sm text-slate-800">
                        Your email address is not verified yet.
                        <button form="send-verification" class="rounded-md text-sm text-brand-600 underline hover:text-brand-800 focus:outline-none focus:ring-2 focus:ring-brand-500">Send a new verification email</button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-2 text-sm font-medium text-emerald-600">A new verification link was sent to your email address.</p>
                    @endif
                </div>
            @endif
        </div>

        {{-- Managed by the administrator --}}
        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label for="username" class="block text-sm font-medium text-slate-700">Username</label>
                <input id="username" type="text" value="{{ $user->username }}" disabled class="{{ $fixed }}">
            </div>
            <div>
                <label for="role" class="block text-sm font-medium text-slate-700">Role</label>
                <input id="role" type="text" value="{{ $user->roleLabel() }}" disabled class="{{ $fixed }}">
            </div>
            @if ($user->company)
                <div class="sm:col-span-2">
                    <label for="company" class="block text-sm font-medium text-slate-700">Company</label>
                    <input id="company" type="text" value="{{ $user->company }}" disabled class="{{ $fixed }}">
                </div>
            @endif
        </div>
        <p class="-mt-2 text-xs text-slate-500">Username, role and company are managed by your administrator.</p>

        <div class="flex items-center gap-4 pt-1">
            <x-primary-button>Save changes</x-primary-button>
        </div>
    </form>
</section>
