{{--
    Edit user window, shared by the Users list and the user details page.
    Open it from anywhere with:  $dispatch('edit-user', { id, name, username, email, company, role, self, action, avatar, initials })
--}}
@php
    $bag      = $errors->getBag('edit');
    $reopenId = session('edit_user_id');
    $target   = $reopenId ? \App\Models\User::find($reopenId) : null;
    $blank    = ['id' => null, 'name' => '', 'username' => '', 'email' => '', 'company' => '', 'role' => 'user', 'self' => false, 'action' => '', 'avatar' => null, 'initials' => ''];
    $pick     = fn ($u) => [
        'id' => $u->id, 'name' => $u->name, 'username' => $u->username, 'email' => $u->email, 'company' => $u->company ?? '', 'role' => $u->role,
        'self' => $u->id === auth()->id(), 'action' => route('users.update', $u), 'avatar' => $u->avatarUrl(), 'initials' => $u->initials(),
    ];
    $saved    = $target ? $pick($target) : $blank;
    // After a failed save, show what the person typed (not the saved values) and keep the saved ones to compare against.
    $current  = $target ? array_merge($saved, [
        'name' => old('name', $saved['name']), 'username' => old('username', $saved['username']), 'email' => old('email', $saved['email']),
        'company' => old('company', $saved['company']), 'role' => old('role', $saved['role']),
    ]) : $blank;
    $ok  = 'border-slate-300 focus:border-brand-500 focus:ring-brand-500';
    $bad = 'border-red-400 focus:border-red-500 focus:ring-red-500';
@endphp

<div x-data="{
        u: @js($current),
        orig: @js($saved),
        loading: false,
        open(d) { this.u = { ...d }; this.orig = { ...d }; this.loading = false; this.$dispatch('open-modal', 'edit-user'); },
        get dirty() { return ['name', 'username', 'email', 'company', 'role'].some(k => (this.u[k] ?? '') !== (this.orig[k] ?? '')); },
        get signInChanged() { return this.u.username !== this.orig.username || this.u.email !== this.orig.email; },
     }"
     x-on:edit-user.window="open($event.detail)">
    <x-modal name="edit-user" :show="$target !== null && $bag->any()" maxWidth="2xl">
        <form method="POST" :action="u.action" novalidate x-on:submit="loading = true">
            @csrf @method('PATCH')

            <div class="flex items-start gap-4 border-b border-slate-100 px-6 py-5">
                <template x-if="u.avatar"><img :src="u.avatar" alt="" class="h-12 w-12 shrink-0 rounded-full object-cover ring-2 ring-white"></template>
                <template x-if="! u.avatar"><span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-brand-800 text-base font-bold uppercase text-white" x-text="u.initials"></span></template>
                <div class="min-w-0 flex-1">
                    <h2 class="text-lg font-bold text-brand-800">Edit user</h2>
                    <p class="truncate text-sm text-slate-500"><span x-text="orig.name"></span> <span x-show="u.self" class="ml-1 rounded bg-brand-50 px-1.5 py-0.5 text-[10px] font-semibold text-brand-700">YOU</span></p>
                </div>
                <button type="button" x-on:click="$dispatch('close')" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600" aria-label="Close">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>
                </button>
            </div>

            <div class="space-y-5 px-6 py-5">
                @if ($bag->any())
                    <div role="alert" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                        Nothing was saved. Please fix the {{ $bag->count() === 1 ? 'highlighted field' : $bag->count() . ' highlighted fields' }} below.
                    </div>
                @endif

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="edit-name" class="mb-1 block text-sm font-medium text-slate-700">Full name</label>
                        <input id="edit-name" name="name" x-model="u.name" required autocomplete="off" class="w-full rounded-lg {{ $bag->has('name') ? $bad : $ok }}">
                        @if ($bag->has('name')) <p role="alert" class="mt-1.5 text-sm text-red-600">{{ $bag->first('name') }}</p> @endif
                    </div>
                    <div>
                        <label for="edit-company" class="mb-1 block text-sm font-medium text-slate-700">Company <span class="font-normal text-slate-400">(optional)</span></label>
                        <input id="edit-company" name="company" x-model="u.company" autocomplete="off" class="w-full rounded-lg {{ $bag->has('company') ? $bad : $ok }}">
                        @if ($bag->has('company')) <p role="alert" class="mt-1.5 text-sm text-red-600">{{ $bag->first('company') }}</p> @endif
                    </div>
                    <div>
                        <label for="edit-email" class="mb-1 block text-sm font-medium text-slate-700">Email</label>
                        <input id="edit-email" name="email" type="email" x-model="u.email" required autocapitalize="none" autocomplete="off" class="w-full rounded-lg {{ $bag->has('email') ? $bad : $ok }}">
                        @if ($bag->has('email')) <p role="alert" class="mt-1.5 text-sm text-red-600">{{ $bag->first('email') }}</p> @endif
                    </div>
                    <div>
                        <label for="edit-username" class="mb-1 block text-sm font-medium text-slate-700">Username</label>
                        <input id="edit-username" name="username" x-model="u.username" required autocapitalize="none" spellcheck="false" autocomplete="off" class="w-full rounded-lg {{ $bag->has('username') ? $bad : $ok }}">
                        @if ($bag->has('username')) <p role="alert" class="mt-1.5 text-sm text-red-600">{{ $bag->first('username') }}</p> @else <p class="mt-1.5 text-xs text-slate-400">Letters, numbers, . - _</p> @endif
                    </div>
                </div>

                <x-users.role-picker :roles="$roles" model="u.role" disabled="u.self" :error="$bag->first('role')" />

                <div x-show="signInChanged" x-cloak class="flex gap-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    <svg class="mt-0.5 h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 9v4m0 4h.01M10.3 3.9 2.4 18a2 2 0 0 0 1.7 3h15.8a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/></svg>
                    <p>Their sign-in details are changing. Let them know the new username or email so they can still sign in.</p>
                </div>
            </div>

            <div class="flex items-center justify-between gap-3 border-t border-slate-100 bg-slate-50/70 px-6 py-4">
                <p class="text-xs text-slate-500" x-text="dirty ? 'You have unsaved changes.' : 'Change something to enable saving.'"></p>
                <div class="flex gap-2">
                    <button type="button" x-on:click="$dispatch('close')" class="rounded-lg bg-white px-4 py-2 text-sm font-medium text-slate-700 ring-1 ring-slate-300 hover:bg-slate-50">Cancel</button>
                    <button type="submit" :disabled="! dirty || loading" class="rounded-lg bg-brand-800 px-5 py-2 text-sm font-semibold text-white hover:bg-brand-700 disabled:cursor-not-allowed disabled:opacity-50">
                        <span x-text="loading ? 'Saving...' : 'Save changes'">Save changes</span>
                    </button>
                </div>
            </div>
        </form>
    </x-modal>
</div>
