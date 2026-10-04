@php
    $bag = $errors->updatePassword;

    // [alpine key, field name, label, autocomplete]
    $fields = [
        ['current', 'current_password',      'Current password',     'current-password'],
        ['pw',      'password',              'New password',         'new-password'],
        ['confirm', 'password_confirmation', 'Confirm new password', 'new-password'],
    ];

    $serverErrors = [
        'current' => $bag->first('current_password'),
        'pw'      => $bag->first('password'),
        'confirm' => $bag->first('password_confirmation'),
    ];
@endphp

<section>
    <header>
        <h2 class="text-lg font-semibold text-brand-800">Change password</h2>
        <p class="mt-1 text-sm text-slate-500">Choose a password you don't use anywhere else. You'll use it the next time you sign in.</p>
    </header>

    @if (session('status') === 'password-updated')
        <x-form-alert type="success" title="Password updated" class="mt-5">Your new password is active.</x-form-alert>
    @endif

    @if ($bag->any())
        <x-form-alert type="error" title="We couldn't update your password" class="mt-5">Check the highlighted fields below, then try again.</x-form-alert>
    @endif

    <form method="POST" action="{{ route('password.update') }}" novalidate class="mt-6 space-y-5"
          x-data="{
              current: '', pw: '', confirm: '', attempted: false, submitting: false,
              show: { current: false, pw: false, confirm: false },
              server: @js($serverErrors),
              get rules() {
                  return [
                      { label: 'At least 8 characters', ok: this.pw.length >= 8 },
                      { label: 'Uppercase and lowercase letters', ok: /\p{Ll}/u.test(this.pw) && /\p{Lu}/u.test(this.pw) },
                      { label: 'At least one number', ok: /\d/.test(this.pw) },
                      { label: 'Different from your current password', ok: this.pw.length > 0 && this.pw !== this.current },
                  ];
              },
              get allMet() { return this.rules.every(r => r.ok); },
              get matches() { return this.confirm.length > 0 && this.confirm === this.pw; },
              get level() {
                  if (!this.pw) return 0;
                  if (!this.allMet) return 1;
                  const long = this.pw.length >= 12, symbol = /[^\p{L}\d]/u.test(this.pw);
                  return long && symbol ? 4 : (long || symbol ? 3 : 2);
              },
              get levelLabel() { return ['', 'Too weak', 'Fair', 'Good', 'Strong'][this.level]; },
              get levelText() { return ['', 'text-red-600', 'text-amber-600', 'text-blue-600', 'text-emerald-600'][this.level]; },
              get levelBar() { return ['', 'bg-red-500', 'bg-amber-500', 'bg-blue-500', 'bg-emerald-500'][this.level]; },
              get hint() {
                  return ['', 'Add the missing items below.', 'Meets the requirements. A symbol or 12+ characters makes it stronger.', 'Good. Adding a symbol and 12+ characters makes it strong.', 'Great choice.'][this.level];
              },
              client(k) {
                  if (!this.attempted) return '';
                  if (k === 'current') return this.current ? '' : 'Enter your current password.';
                  if (k === 'pw') return this.allMet ? '' : (this.pw ? 'Your new password does not meet all the requirements below.' : 'Enter a new password.');
                  return this.matches ? '' : (this.confirm ? 'The two passwords do not match.' : 'Re-enter your new password to confirm it.');
              },
              err(k) { return this.server[k] || this.client(k); },
              edit(k) { this.server[k] = ''; if (k === 'pw') this.server.confirm = ''; },
              submit(e) {
                  this.attempted = true;
                  if (!this.current || !this.allMet || !this.matches) { e.preventDefault(); this.$nextTick(() => this.$el.querySelector('[aria-invalid=true]')?.focus()); return; }
                  this.submitting = true;
              },
          }"
          @submit="submit($event)">
        @csrf
        @method('put')

        @foreach ($fields as [$key, $name, $label, $auto])
            <div>
                <label for="pw_{{ $key }}" class="block text-sm font-medium text-slate-700">{{ $label }}</label>
                <div class="relative mt-1.5">
                    <input id="pw_{{ $key }}" name="{{ $name }}" x-model="{{ $key }}" @input="edit('{{ $key }}')"
                           :type="show.{{ $key }} ? 'text' : 'password'" autocomplete="{{ $auto }}"
                           :aria-invalid="err('{{ $key }}') ? 'true' : 'false'" aria-describedby="pw_{{ $key }}_msg"
                           :class="err('{{ $key }}') ? 'border-red-400 focus:border-red-500 focus:ring-red-500' : 'border-slate-300 focus:border-brand-500 focus:ring-brand-500'"
                           class="block w-full rounded-lg py-2.5 pr-11 text-sm shadow-sm focus:ring-1">
                    <button type="button" @click="show.{{ $key }} = !show.{{ $key }}"
                            :aria-label="show.{{ $key }} ? 'Hide password' : 'Show password'" :aria-pressed="show.{{ $key }}" :title="show.{{ $key }} ? 'Hide password' : 'Show password'"
                            class="absolute inset-y-0 right-0 flex w-11 items-center justify-center rounded-r-lg text-slate-500 transition hover:text-brand-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-brand-500">
                        <svg x-show="!show.{{ $key }}" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12z"/><circle cx="12" cy="12" r="3"/></svg>
                        <svg x-show="show.{{ $key }}" x-cloak class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 3l18 18M10.6 6a9.6 9.6 0 0 1 1.4-.1c6 0 9.5 6.1 9.5 6.1a16 16 0 0 1-3.2 3.9M6.6 7.6A16.4 16.4 0 0 0 2.5 12S6 18.1 12 18.1c1.4 0 2.7-.3 3.8-.8M9.9 9.9a3 3 0 0 0 4.2 4.2"/></svg>
                    </button>
                </div>

                <div id="pw_{{ $key }}_msg">
                    {{-- Error (from the server or from this page) --}}
                    <p x-show="err('{{ $key }}')" x-cloak class="mt-1.5 flex items-start gap-1.5 text-sm text-red-600">
                        <svg class="mt-0.5 h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v4.5M12 16h.01"/></svg>
                        <span x-text="err('{{ $key }}')"></span>
                    </p>

                    @if ($key === 'pw')
                        {{-- Strength meter + live checklist --}}
                        <div x-show="pw.length > 0" x-cloak class="mt-3 rounded-xl bg-slate-50 p-4">
                            <div class="flex items-center gap-3">
                                <div class="flex flex-1 gap-1.5" aria-hidden="true">
                                    <template x-for="i in 4" :key="i">
                                        <span class="h-1.5 flex-1 rounded-full transition-colors duration-200" :class="i <= level ? levelBar : 'bg-slate-200'"></span>
                                    </template>
                                </div>
                                <span class="w-16 text-right text-xs font-semibold" :class="levelText" x-text="levelLabel" aria-live="polite"></span>
                            </div>
                            <p class="mt-2 text-xs text-slate-500" x-text="hint"></p>
                            <ul class="mt-3 space-y-1.5">
                                <template x-for="r in rules" :key="r.label">
                                    <li class="flex items-center gap-2 text-sm" :class="r.ok ? 'text-emerald-700' : 'text-slate-500'">
                                        <svg x-show="r.ok" class="h-4 w-4 shrink-0 text-emerald-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.86-9.64a.75.75 0 0 0-1.22-.87l-3.2 4.48-1.65-1.65a.75.75 0 0 0-1.06 1.06l2.3 2.3a.75.75 0 0 0 1.14-.1l3.69-5.22Z" clip-rule="evenodd"/></svg>
                                        <span x-show="!r.ok" class="flex h-4 w-4 shrink-0 items-center justify-center" aria-hidden="true"><span class="h-2.5 w-2.5 rounded-full border-2 border-slate-300"></span></span>
                                        <span x-text="r.label"></span>
                                        <span class="sr-only" x-text="r.ok ? '(done)' : '(not yet)'"></span>
                                    </li>
                                </template>
                            </ul>
                        </div>
                    @endif

                    @if ($key === 'confirm')
                        <p x-show="confirm.length > 0 && !err('confirm')" x-cloak class="mt-1.5 flex items-center gap-1.5 text-sm" :class="matches ? 'text-emerald-600' : 'text-slate-500'" aria-live="polite">
                            <svg x-show="matches" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.86-9.64a.75.75 0 0 0-1.22-.87l-3.2 4.48-1.65-1.65a.75.75 0 0 0-1.06 1.06l2.3 2.3a.75.75 0 0 0 1.14-.1l3.69-5.22Z" clip-rule="evenodd"/></svg>
                            <span x-text="matches ? 'Passwords match' : 'Does not match yet'"></span>
                        </p>
                    @endif
                </div>
            </div>
        @endforeach

        <div class="flex flex-wrap items-center gap-x-4 gap-y-2 pt-1">
            <button type="submit" :disabled="submitting"
                    class="inline-flex items-center justify-center gap-2 rounded-lg bg-brand-800 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2 disabled:cursor-wait disabled:opacity-70">
                <svg x-show="submitting" x-cloak class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3" class="opacity-25"/><path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>
                <span x-text="submitting ? 'Updating...' : 'Update password'">Update password</span>
            </button>
            <p class="text-xs text-slate-500">Forgot your current password? Ask your IT administrator to reset it.</p>
        </div>
    </form>
</section>
