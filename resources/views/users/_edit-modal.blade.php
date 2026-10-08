{{--
    Edit user window, shared by the Users list and the user details page.
    Open it from anywhere with:  $dispatch('edit-user', { id, name, username, email, company, role, self, action, avatar, initials })
--}}
@php
    $bag      = $errors->getBag('edit');
    $reopenId = session('edit_user_id');
    $target   = $reopenId ? \App\Models\User::find($reopenId) : null;
    $blank    = ['id' => null, 'name' => '', 'username' => '', 'email' => '', 'company' => '', 'company_id' => '', 'role' => 'user', 'self' => false, 'action' => '', 'avatar' => null, 'initials' => '', 'photo_url' => '', 'photo_remove_url' => ''];
    $pick     = fn ($u) => [
        'id' => $u->id, 'name' => $u->name, 'username' => $u->username, 'email' => $u->email, 'company' => $u->company ?? '', 'company_id' => (string) ($u->company_id ?? (in_array($u->role, ['admin', 'it_support']) ? 'jms' : '')), 'role' => $u->role,
        'self' => $u->id === auth()->id(), 'action' => route('users.update', $u), 'avatar' => $u->avatarUrl(), 'initials' => $u->initials(),
        'photo_url' => route('users.avatar.update', $u), 'photo_remove_url' => route('users.avatar.destroy', $u),
    ];
    $saved    = $target ? $pick($target) : $blank;
    // After a failed save, show what the person typed (not the saved values) and keep the saved ones to compare against.
    $current  = $target ? array_merge($saved, [
        'name' => old('name', $saved['name']), 'username' => old('username', $saved['username']), 'email' => old('email', $saved['email']),
        'company' => $saved['company'], 'company_id' => old('jms_team') ? 'jms' : (string) old('company_id', $saved['company_id']), 'role' => old('role', $saved['role']),
    ]) : $blank;
    $isSuper = auth()->user()->role === 'super_admin';
    $companyOptions = $isSuper ? \App\Models\Company::orderBy('name')->get(['id', 'name']) : collect();
    $ok  = 'border-slate-300 focus:border-brand-500 focus:ring-brand-500';
    $bad = 'border-red-400 focus:border-red-500 focus:ring-red-500';
@endphp

<div x-data="{
        u: @js($current),
        orig: @js($saved),
        loading: false, uploading: false, photoError: '', confirmRemove: false,
        open(d) { this.u = { ...d }; this.orig = { ...d }; this.loading = false; this.uploading = false; this.photoError = ''; this.confirmRemove = false; this.$dispatch('open-modal', 'edit-user'); },
        choosePhoto() { document.getElementById('edit-photo-file').click(); },
        pickPhoto(e) {
            const f = e.target.files[0];
            this.photoError = '';
            if (!f) return;
            if (!['image/jpeg', 'image/png', 'image/webp'].includes(f.type)) { this.photoError = 'Choose a JPG, PNG or WebP image.'; e.target.value = ''; return; }
            if (f.size > 2 * 1024 * 1024) { this.photoError = 'That photo is over 2 MB. Choose a smaller one.'; e.target.value = ''; return; }
            this.u.avatar = URL.createObjectURL(f);
            this.uploading = true;
            document.getElementById('edit-photo-form').submit();
        },
        get dirty() { return ['name', 'username', 'email', 'company_id', 'role'].some(k => (this.u[k] ?? '') !== (this.orig[k] ?? '')); },
        get signInChanged() { return this.u.username !== this.orig.username || this.u.email !== this.orig.email; },
     }"
     x-on:edit-user.window="open($event.detail)">
    <x-modal name="edit-user" :show="$target !== null && $bag->any()" maxWidth="2xl">
        <form method="POST" :action="u.action" novalidate x-on:submit="loading = true">
            @csrf @method('PATCH')

            <div class="flex items-start gap-4 border-b border-slate-100 px-6 py-5">
                {{-- Photo: the camera button (or "Change photo") opens the file picker and the photo is saved straight away --}}
                <div class="relative shrink-0">
                    <template x-if="u.avatar"><img :src="u.avatar" x-on:error="u.avatar = null" alt="" class="h-16 w-16 rounded-full object-cover shadow-sm ring-2 ring-white" :class="uploading && 'opacity-50'"></template>
                    <template x-if="! u.avatar"><span class="flex h-16 w-16 items-center justify-center rounded-full bg-brand-800 text-xl font-bold uppercase text-white shadow-sm" x-text="u.initials"></span></template>
                    <span x-show="uploading" x-cloak class="absolute inset-0 flex items-center justify-center">
                        <svg class="h-6 w-6 animate-spin text-brand-700" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg>
                    </span>
                    <button type="button" x-show="! u.self && ! uploading" x-on:click="choosePhoto()" title="Change photo" aria-label="Change photo"
                            class="absolute -bottom-1 -right-1 flex h-7 w-7 items-center justify-center rounded-full border-2 border-white bg-brand-800 text-white shadow transition hover:bg-brand-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2">
                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 8h3l1.5-2h7L17 8h3a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V9a1 1 0 0 1 1-1z"/><circle cx="12" cy="13.5" r="3.5"/></svg>
                    </button>
                </div>
                <div class="min-w-0 flex-1">
                    <h2 class="text-lg font-bold text-brand-800">Edit user</h2>
                    <p class="truncate text-sm text-slate-500"><span x-text="orig.name"></span> <span x-show="u.self" class="ml-1 rounded bg-brand-50 px-1.5 py-0.5 text-[10px] font-semibold text-brand-700">YOU</span></p>

                    <div x-show="! u.self" class="mt-1.5 text-xs">
                        <p x-show="uploading" x-cloak class="font-medium text-slate-500">Uploading photo...</p>
                        <div x-show="! uploading && ! confirmRemove" class="flex flex-wrap items-center gap-x-3 gap-y-1">
                            <button type="button" x-on:click="choosePhoto()" class="font-semibold text-brand-600 hover:underline" x-text="orig.avatar ? 'Change photo' : 'Add photo'"></button>
                            <button type="button" x-show="orig.avatar" x-on:click="confirmRemove = true" class="font-semibold text-red-600 hover:underline">Remove</button>
                            <span class="text-slate-400">JPG, PNG or WebP, up to 2 MB</span>
                        </div>
                        <div x-show="confirmRemove && ! uploading" x-cloak class="flex items-center gap-3" role="alertdialog" aria-label="Remove profile photo">
                            <span class="font-medium text-slate-700">Remove this photo?</span>
                            <button type="submit" form="edit-photo-remove-form" class="font-semibold text-red-600 hover:underline">Yes, remove</button>
                            <button type="button" x-on:click="confirmRemove = false" class="font-semibold text-slate-500 hover:underline">Keep</button>
                        </div>
                        <p x-show="photoError" x-cloak x-text="photoError" role="alert" class="mt-1 font-medium text-red-600"></p>
                    </div>
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
                        @if ($isSuper)
                            <label for="edit-company" class="mb-1 block text-sm font-medium text-slate-700">Company</label>
                            <select id="edit-company" name="company_id" x-model="u.company_id" required class="w-full rounded-lg {{ $bag->has('company_id') ? $bad : $ok }}">
                                <option value="">Choose a company</option>
                                <option value="jms" x-bind:disabled="u.role === 'user'" x-bind:hidden="u.role === 'user'">JMS One IT (our team)</option>
                                @foreach ($companyOptions as $c)
                                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                                @endforeach
                            </select>
                            @if ($bag->has('company_id')) <p role="alert" class="mt-1.5 text-sm text-red-600">{{ $bag->first('company_id') }}</p> @endif
                        @else
                            <label class="mb-1 block text-sm font-medium text-slate-700">Company</label>
                            <p class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-600" x-text="u.company || 'Not set'"></p>
                        @endif
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

        {{-- Photo forms sit outside the edit form (forms cannot be nested). The buttons above trigger them. --}}
        <form id="edit-photo-form" method="POST" :action="u.photo_url" enctype="multipart/form-data" class="hidden">
            @csrf
            <input id="edit-photo-file" type="file" name="avatar" accept="image/png,image/jpeg,image/webp" class="sr-only" tabindex="-1" aria-label="Choose a photo" x-on:change="pickPhoto($event)">
        </form>
        <form id="edit-photo-remove-form" method="POST" :action="u.photo_remove_url" class="hidden">
            @csrf @method('DELETE')
        </form>
    </x-modal>
</div>
