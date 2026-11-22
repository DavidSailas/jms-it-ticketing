<x-app-layout>
    <x-slot name="header">{{ $role->exists ? ($role->isLocked() ? $role->name : 'Edit role') : 'New role' }}</x-slot>

    @php
        $isNew    = ! $role->exists;
        $locked   = $role->exists && $role->isLocked();
        $builtIn  = $role->exists && $role->is_system;
        $card     = 'rounded-2xl border border-slate-200 bg-white shadow-sm';
        $field    = 'w-full rounded-lg border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500';
        $selected = old('permissions', $role->slug === 'super_admin' ? \App\Support\Permissions::keys() : ($role->permissions ?? []));
    @endphp

    <a href="{{ route('roles.index') }}" class="mb-4 inline-flex items-center gap-1.5 text-sm font-medium text-slate-500 hover:text-brand-700">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m15 6-6 6 6 6"/></svg>
        All roles
    </a>

    @if ($locked)
        <div class="mb-4 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
            {{ $role->slug === 'super_admin' ? 'Super Admin always has full access and cannot be changed, so nobody can lock themselves out.' : 'Customers log and follow their own tickets. There is nothing extra to give them, so this role cannot be changed.' }}
        </div>
    @elseif ($builtIn)
        <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            This is a built-in role. It cannot be renamed or deleted, and changes apply to <strong>everyone</strong> who has it. To give some people different permissions, create a new role instead.
        </div>
    @endif

    <form method="POST" action="{{ $isNew ? route('roles.store') : route('roles.update', $role) }}"
          x-data="{
              base: @js(old('base_role', $role->base_role)),
              allowed: @js($byBase),
              checked: @js(array_values($selected)),
              has(k) { return (this.allowed[this.base] || []).includes(k); },
              selectAll(keys) { keys.forEach(k => { if (this.has(k) && !this.checked.includes(k)) this.checked.push(k); }); },
              clearAll(keys) { this.checked = this.checked.filter(k => !keys.includes(k)); },
              get count() { return this.checked.filter(k => this.has(k)).length; },
              get total() { return (this.allowed[this.base] || []).length; }
          }">
        @csrf
        @unless ($isNew) @method('PATCH') @endunless

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">

                {{-- Basics --}}
                <section class="{{ $card }} space-y-4 p-5 sm:p-6">
                    <h3 class="font-semibold text-brand-800">Role details</h3>

                    <div>
                        <label for="name" class="mb-1.5 block text-sm font-medium text-slate-700">Role name</label>
                        <input id="name" type="text" name="name" value="{{ old('name', $role->name) }}" maxlength="60" required
                               @disabled($builtIn) placeholder="e.g. Dispatcher, Senior Engineer" class="{{ $field }} disabled:bg-slate-50 disabled:text-slate-500">
                        @error('name') <p role="alert" class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="description" class="mb-1.5 block text-sm font-medium text-slate-700">Description <span class="font-normal text-slate-400">(optional)</span></label>
                        <input id="description" type="text" name="description" value="{{ old('description', $role->description) }}" maxlength="200"
                               @disabled($locked) placeholder="What is this role for?" class="{{ $field }} disabled:bg-slate-50 disabled:text-slate-500">
                        @error('description') <p role="alert" class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <span class="mb-1.5 block text-sm font-medium text-slate-700">Account type</span>
                        @if ($isNew)
                            <div class="grid gap-2 sm:grid-cols-2" role="radiogroup" aria-label="Account type">
                                @foreach (\App\Models\Role::BASES as $value => $label)
                                    <label class="cursor-pointer">
                                        <input type="radio" name="base_role" value="{{ $value }}" x-model="base" class="peer sr-only">
                                        <span class="block rounded-xl border border-slate-200 bg-white p-3 transition hover:border-slate-300 peer-checked:border-brand-600 peer-checked:bg-brand-50 peer-checked:ring-2 peer-checked:ring-brand-100 peer-focus-visible:ring-2 peer-focus-visible:ring-brand-500">
                                            <span class="block text-sm font-semibold text-slate-800">{{ $label }}</span>
                                            <span class="mt-0.5 block text-xs text-slate-500">
                                                {{ $value === 'admin' ? 'Sees every ticket and partner company. For dispatchers and managers.' : 'Sees only the tickets assigned to them. For engineers.' }}
                                            </span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                            @error('base_role') <p role="alert" class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                            <p class="mt-1.5 text-xs text-slate-400">This decides what people in the role can see, so it cannot be changed later.</p>
                        @else
                            <p class="text-sm text-slate-700">{{ $role->baseLabel() }}</p>
                        @endif
                    </div>
                </section>

                {{-- Permissions --}}
                <section class="{{ $card }} p-5 sm:p-6" aria-labelledby="perm-heading">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h3 id="perm-heading" class="font-semibold text-brand-800">What this role can do</h3>
                        @unless ($locked)
                            <span class="rounded-full bg-brand-50 px-2.5 py-0.5 text-xs font-semibold text-brand-700" x-text="count + ' of ' + total + ' selected'"></span>
                        @endunless
                    </div>
                    @error('permissions') <p role="alert" class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                    @if ($role->base_role === 'user')
                        <p class="mt-3 text-sm text-slate-500">Customers have no extra permissions. They can always log, reschedule and follow their own tickets, and cancel one before an engineer is assigned.</p>
                    @endif

                    <div class="mt-4 space-y-6">
                        @foreach ($groups as $group => $items)
                            @php($keys = array_keys($items))
                            <div x-show="@js($keys).some(k => has(k))" x-cloak>
                                <div class="mb-2 flex items-center justify-between">
                                    <p class="text-xs font-bold uppercase tracking-wide text-slate-500">{{ $group }}</p>
                                    @unless ($locked)
                                        <span class="text-xs">
                                            <button type="button" class="font-medium text-brand-700 hover:underline" @click="selectAll(@js($keys))">Select all</button>
                                            <span class="text-slate-300"> &middot; </span>
                                            <button type="button" class="font-medium text-slate-500 hover:underline" @click="clearAll(@js($keys))">Clear</button>
                                        </span>
                                    @endunless
                                </div>
                                <ul class="divide-y divide-slate-100 rounded-xl border border-slate-200">
                                    @foreach ($items as $key => [$label, $help, $bases])
                                        <li x-show="has('{{ $key }}')" x-cloak>
                                            <label class="flex cursor-pointer items-start gap-3 px-4 py-3 hover:bg-slate-50 {{ $locked ? 'cursor-default' : '' }}">
                                                <input type="checkbox" name="permissions[]" value="{{ $key }}" x-model="checked" x-bind:disabled="!has('{{ $key }}')"
                                                       @disabled($locked) class="mt-0.5 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                                                <span class="min-w-0">
                                                    <span class="block text-sm font-medium text-slate-800">{{ $label }}</span>
                                                    <span class="block text-xs text-slate-500">{{ $help }}</span>
                                                </span>
                                            </label>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endforeach
                    </div>
                </section>

                @unless ($locked)
                    <div class="flex items-center justify-end gap-2">
                        <a href="{{ route('roles.index') }}" class="rounded-lg bg-white px-4 py-2.5 text-sm font-medium text-slate-700 ring-1 ring-slate-300 hover:bg-slate-50">Cancel</a>
                        <button class="rounded-lg bg-brand-800 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-brand-700">{{ $isNew ? 'Create role' : 'Save changes' }}</button>
                    </div>
                @endunless
            </div>

            {{-- Right column: tips --}}
            <aside class="space-y-4">
                <div class="{{ $card }} p-5 text-sm text-slate-600">
                    <h3 class="font-semibold text-brand-800">How roles work</h3>
                    <ul class="mt-2 list-disc space-y-1.5 pl-5">
                        <li><strong>Account type</strong> decides what people can see.</li>
                        <li><strong>Permissions</strong> decide what they can do.</li>
                        <li>Changes apply the next time the person opens a page.</li>
                        <li>Super Admin is never limited.</li>
                    </ul>
                </div>
            </aside>
        </div>
    </form>

    {{-- Members of a custom role (separate forms, so they sit outside the main form) --}}
    @if ($role->exists && ! $role->is_system)
        <section class="{{ $card }} mt-6 p-5 sm:p-6" aria-labelledby="members-heading">
            <h3 id="members-heading" class="font-semibold text-brand-800">People with this role <span class="text-sm font-normal text-slate-400">({{ $members->count() }})</span></h3>

            <form method="POST" action="{{ route('roles.members.add', $role) }}" class="mt-3 flex flex-col gap-2 sm:flex-row">
                @csrf
                <select name="user_id" required class="{{ $field }} sm:max-w-sm" aria-label="Add a person">
                    <option value="">Add a {{ strtolower($role->baseLabel()) }} account...</option>
                    @foreach ($candidates as $c)
                        <option value="{{ $c->id }}">{{ $c->name }} ({{ $c->email }})</option>
                    @endforeach
                </select>
                <button class="rounded-lg bg-brand-800 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">Add to role</button>
            </form>
            @error('user_id') <p role="alert" class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
            @if ($candidates->isEmpty())
                <p class="mt-2 text-xs text-slate-500">No other {{ strtolower($role->baseLabel()) }} accounts to add. Create the account under Users first.</p>
            @endif

            <ul class="mt-4 divide-y divide-slate-100 rounded-xl border border-slate-200">
                @forelse ($members as $m)
                    <li class="flex items-center justify-between gap-3 px-4 py-2.5">
                        <span class="min-w-0">
                            <span class="block truncate text-sm font-medium text-slate-800">{{ $m->name }}</span>
                            <span class="block truncate text-xs text-slate-500">{{ $m->email }}</span>
                        </span>
                        <form method="POST" action="{{ route('roles.members.remove', [$role, $m]) }}" onsubmit="return confirm('Remove {{ addslashes($m->name) }} from this role?')">
                            @csrf @method('DELETE')
                            <button class="rounded-lg px-3 py-1.5 text-xs font-medium text-rose-700 ring-1 ring-rose-200 hover:bg-rose-50">Remove</button>
                        </form>
                    </li>
                @empty
                    <li class="px-4 py-6 text-center text-sm text-slate-500">Nobody has this role yet.</li>
                @endforelse
            </ul>
            <p class="mt-2 text-xs text-slate-400">Removing someone puts them back on the standard role for their account type.</p>
        </section>
    @endif
</x-app-layout>
