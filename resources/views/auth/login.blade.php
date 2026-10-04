<x-guest-layout>
    <div class="mb-5 mt-4 text-center [@media(max-height:700px)]:mb-4 [@media(max-height:700px)]:mt-2">
        <h1 class="text-2xl font-extrabold tracking-tight text-brand-800">Sign in</h1>
        <p class="mt-1 text-sm text-slate-500">Use your JMS One IT account.</p>
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif

    @error('credentials')
        <div role="alert" class="mb-4 flex items-start gap-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            <svg class="mt-0.5 h-5 w-5 shrink-0 text-red-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"/></svg>
            <span>{{ $message }}</span>
        </div>
    @enderror

    <form method="POST" action="{{ route('login') }}" novalidate class="space-y-4"
          x-data="{
              show: false, loading: false, caps: false, help: false,
              login: @js(old('login', '')),
              errors: { login: @js($errors->first('login')), password: @js($errors->first('password')) },
              checkLogin() {
                  const v = this.$refs.login.value.trim();
                  if (!v) this.errors.login = 'Enter your email address or username.';
                  else if (v.includes('@') && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v)) this.errors.login = 'Enter a valid email address.';
                  else this.errors.login = '';
                  return !this.errors.login;
              },
              checkPassword() {
                  this.errors.password = this.$refs.password.value ? '' : 'Enter your password.';
                  return !this.errors.password;
              },
              submit(e) {
                  const a = this.checkLogin(), b = this.checkPassword();
                  if (!(a && b)) { e.preventDefault(); (a ? this.$refs.password : this.$refs.login).focus(); return; }
                  this.loading = true;
              }
          }"
          @submit="submit($event)" @pageshow.window="loading = false">
        @csrf

        {{-- Email or username --}}
        <div>
            <label for="login" class="mb-1.5 block text-sm font-medium text-slate-700">Email or username</label>
            <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/></svg>
                </span>
                <input id="login" name="login" type="text" x-ref="login" x-model="login" autofocus autocomplete="username" autocapitalize="none" spellcheck="false"
                       placeholder="Enter your email or username"
                       @input="errors.login = ''" @blur="if (login.trim()) checkLogin()"
                       :aria-invalid="errors.login ? 'true' : 'false'" aria-describedby="login-error"
                       :class="errors.login || {{ $errors->has('credentials') ? 'true' : 'false' }} ? 'border-red-400 focus:border-red-500 focus:ring-red-500' : 'border-slate-300 focus:border-brand-500 focus:ring-brand-500'"
                       class="block w-full rounded-lg py-2.5 pl-11 pr-3 text-sm shadow-sm placeholder:text-slate-400">
            </div>
            <p id="login-error" x-show="errors.login" x-cloak x-text="errors.login" role="alert" class="mt-1.5 text-sm text-red-600"></p>
        </div>

        {{-- Password --}}
        <div>
            <label for="password" class="mb-1.5 block text-sm font-medium text-slate-700">Password</label>
            <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z"/></svg>
                </span>
                <input id="password" name="password" :type="show ? 'text' : 'password'" x-ref="password" autocomplete="current-password"
                       placeholder="Enter your password"
                       @input="errors.password = ''" @keyup="caps = $event.getModifierState && $event.getModifierState('CapsLock')" @blur="caps = false"
                       :aria-invalid="errors.password ? 'true' : 'false'" aria-describedby="password-error"
                       :class="errors.password || {{ $errors->has('credentials') ? 'true' : 'false' }} ? 'border-red-400 focus:border-red-500 focus:ring-red-500' : 'border-slate-300 focus:border-brand-500 focus:ring-brand-500'"
                       class="block w-full rounded-lg py-2.5 pl-11 pr-11 text-sm shadow-sm placeholder:text-slate-400">
                <button type="button" @click="show = !show" :aria-label="show ? 'Hide password' : 'Show password'" :aria-pressed="show" :title="show ? 'Hide password' : 'Show password'"
                        class="absolute inset-y-0 right-0 flex w-11 items-center justify-center rounded-r-lg text-slate-500 transition hover:text-brand-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-brand-500">
                    <svg x-show="!show" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12z"/><circle cx="12" cy="12" r="3"/></svg>
                    <svg x-show="show" x-cloak class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 3l18 18M10.6 6a9.6 9.6 0 0 1 1.4-.1c6 0 9.5 6.1 9.5 6.1a16 16 0 0 1-3.2 3.9M6.6 7.6A16.4 16.4 0 0 0 2.5 12S6 18.1 12 18.1c1.4 0 2.7-.3 3.8-.8M9.9 9.9a3 3 0 0 0 4.2 4.2"/></svg>
                </button>
            </div>
            <p id="password-error" x-show="errors.password" x-cloak x-text="errors.password" role="alert" class="mt-1.5 text-sm text-red-600"></p>
            <p x-show="caps" x-cloak class="mt-1.5 text-sm text-amber-600">Caps Lock is on.</p>
        </div>

        <div>
            <div class="flex items-center justify-between gap-4">
                <label class="flex cursor-pointer items-center gap-2 text-sm text-slate-600">
                    <input type="checkbox" name="remember" value="1" @checked(old('remember')) class="h-4 w-4 rounded border-slate-300 text-brand-800 focus:ring-brand-500">
                    Keep me signed in
                </label>
                <button type="button" @click="help = !help" :aria-expanded="help" aria-controls="forgot-help"
                        class="rounded text-sm font-medium text-brand-600 hover:text-brand-800 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500">Forgot password?</button>
            </div>
            <p id="forgot-help" x-show="help" x-cloak class="mt-2.5 rounded-lg bg-brand-50 px-3 py-2.5 text-sm text-brand-800">Your IT administrator resets passwords. Contact JMS One IT and they will set a new one for you.</p>
        </div>

        <button type="submit" :disabled="loading"
                class="flex w-full items-center justify-center gap-2 rounded-lg bg-brand-800 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-70">
            <svg x-show="loading" x-cloak class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg>
            <span x-text="loading ? 'Signing in...' : 'Sign in'">Sign in</span>
        </button>
    </form>

    <p class="mt-5 text-center text-sm text-slate-500">Need an account? Ask your IT administrator.</p>
</x-guest-layout>
