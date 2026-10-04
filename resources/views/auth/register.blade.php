<x-guest-layout>
    <div class="mb-8">
        <h1 class="text-3xl font-extrabold tracking-tight text-brand-800">Create your account</h1>
        <p class="mt-2 text-sm text-slate-500">Sign up to start submitting IT support tickets to our team.</p>
    </div>

    @error('social')
        <div class="mb-5 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">{{ $message }}</div>
    @enderror

    @include('auth.partials.social', ['verb' => 'Sign up with'])

    <form method="POST" action="{{ route('register') }}" class="space-y-5"
          x-data="{
              show: false, loading: false, pw: '',
              get score() {
                  let s = 0;
                  if (this.pw.length >= 8) s++;
                  if (/[a-z]/.test(this.pw) && /[A-Z]/.test(this.pw)) s++;
                  if (/\d/.test(this.pw)) s++;
                  if (/[^A-Za-z0-9]/.test(this.pw)) s++;
                  return s;
              },
              get label() { return ['Too weak', 'Weak', 'Fair', 'Good', 'Strong'][this.score]; }
          }"
          @submit="loading = true" @pageshow.window="loading = false">
        @csrf

        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label for="name" class="mb-1.5 block text-sm font-medium text-slate-700">Full name</label>
                <input id="name" name="name" type="text" value="{{ old('name') }}" required autofocus autocomplete="name" placeholder="Juan Dela Cruz"
                       class="block w-full rounded-lg border-slate-300 py-2.5 text-sm shadow-sm placeholder:text-slate-400 focus:border-brand-500 focus:ring-brand-500 @error('name') border-red-400 @enderror">
                @error('name') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="company" class="mb-1.5 block text-sm font-medium text-slate-700">Company</label>
                <input id="company" name="company" type="text" value="{{ old('company') }}" required autocomplete="organization" placeholder="Your company name"
                       class="block w-full rounded-lg border-slate-300 py-2.5 text-sm shadow-sm placeholder:text-slate-400 focus:border-brand-500 focus:ring-brand-500 @error('company') border-red-400 @enderror">
                @error('company') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <div>
            <label for="email" class="mb-1.5 block text-sm font-medium text-slate-700">Work email</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="username" placeholder="you@company.com"
                   class="block w-full rounded-lg border-slate-300 py-2.5 text-sm shadow-sm placeholder:text-slate-400 focus:border-brand-500 focus:ring-brand-500 @error('email') border-red-400 @enderror">
            @error('email') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="password" class="mb-1.5 block text-sm font-medium text-slate-700">Password</label>
            <div class="relative">
                <input id="password" name="password" :type="show ? 'text' : 'password'" x-model="pw" required autocomplete="new-password" placeholder="At least 8 characters"
                       class="block w-full rounded-lg border-slate-300 py-2.5 pr-11 text-sm shadow-sm placeholder:text-slate-400 focus:border-brand-500 focus:ring-brand-500 @error('password') border-red-400 @enderror">
                <button type="button" @click="show = !show" :aria-label="show ? 'Hide passwords' : 'Show passwords'" :aria-pressed="show" :title="show ? 'Hide passwords' : 'Show passwords'"
                        class="absolute inset-y-0 right-0 flex w-11 items-center justify-center rounded-r-lg text-slate-500 transition hover:text-brand-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-brand-500">
                    <svg x-show="!show" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12z"/><circle cx="12" cy="12" r="3"/></svg>
                    <svg x-show="show" x-cloak class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 3l18 18M10.6 6a9.6 9.6 0 0 1 1.4-.1c6 0 9.5 6.1 9.5 6.1a16 16 0 0 1-3.2 3.9M6.6 7.6A16.4 16.4 0 0 0 2.5 12S6 18.1 12 18.1c1.4 0 2.7-.3 3.8-.8M9.9 9.9a3 3 0 0 0 4.2 4.2"/></svg>
                </button>
            </div>
            <div x-show="pw.length > 0" x-cloak class="mt-2">
                <div class="flex gap-1.5">
                    @foreach ([1, 2, 3, 4] as $bar)
                        <div class="h-1.5 flex-1 rounded-full transition-colors"
                             :class="score >= {{ $bar }} ? (score <= 1 ? 'bg-red-400' : (score == 2 ? 'bg-amber-400' : (score == 3 ? 'bg-sky-500' : 'bg-emerald-500'))) : 'bg-slate-200'"></div>
                    @endforeach
                </div>
                <p class="mt-1 text-xs text-slate-500">Strength: <span class="font-semibold" x-text="label"></span></p>
            </div>
            <p x-show="pw.length === 0" class="mt-1.5 text-xs text-slate-400">Use 8+ characters with upper and lower case, numbers and symbols.</p>
            @error('password') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="password_confirmation" class="mb-1.5 block text-sm font-medium text-slate-700">Confirm password</label>
            <input id="password_confirmation" name="password_confirmation" :type="show ? 'text' : 'password'" required autocomplete="new-password" placeholder="Re-enter your password"
                   class="block w-full rounded-lg border-slate-300 py-2.5 text-sm shadow-sm placeholder:text-slate-400 focus:border-brand-500 focus:ring-brand-500">
        </div>

        <button type="submit" :disabled="loading"
                class="flex w-full items-center justify-center gap-2 rounded-lg bg-brand-800 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-70">
            <svg x-show="loading" x-cloak class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg>
            <span x-text="loading ? 'Creating account...' : 'Create account'">Create account</span>
        </button>
    </form>

    <p class="mt-8 text-center text-sm text-slate-500">
        Already have an account?
        <a href="{{ route('login') }}" class="font-semibold text-brand-600 hover:text-brand-800 hover:underline">Sign in</a>
    </p>
</x-guest-layout>
