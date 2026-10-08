<x-app-layout>
    <x-slot name="header">User details</x-slot>

    @php
        $badge = [
            'user'        => 'bg-slate-100 text-slate-700 ring-slate-200',
            'it_support'  => 'bg-sky-50 text-sky-700 ring-sky-200',
            'admin'       => 'bg-violet-50 text-violet-700 ring-violet-200',
            'super_admin' => 'bg-amber-50 text-amber-800 ring-amber-300',
        ];
        $card      = 'rounded-xl border border-slate-200 bg-white shadow-sm';
        $manageable = in_array($user->role, $roles);
        $payload   = ['id' => $user->id, 'name' => $user->name, 'username' => $user->username, 'email' => $user->email, 'company' => $user->company ?? '', 'company_id' => (string) ($user->company_id ?? (in_array($user->role, ['admin', 'it_support']) ? 'jms' : '')), 'role' => $user->role, 'self' => $user->id === auth()->id(), 'action' => route('users.update', $user), 'avatar' => $user->avatarUrl(), 'initials' => $user->initials(), 'photo_url' => route('users.avatar.update', $user), 'photo_remove_url' => route('users.avatar.destroy', $user)];
        $dateTime  = fn ($d) => $d ? $d->format('M j, Y, g:i A') : null;
        $ticketsOf = ['it_support' => 'Assigned tickets', 'admin' => 'Tickets accepted'][$user->role] ?? 'Submitted tickets';
        $lastSeen  = $activity->first()?->created_at;
        $signIn    = $user->provider ? ucfirst($user->provider) . ' account' : 'Username and password';

        $tiles = [
            [$ticketsOf,  $summary['total'],     'border-t-brand-500',   'text-slate-900'],
            ['Active now', $summary['active'],   'border-t-amber-400',   'text-amber-600'],
            ['Resolved',  $summary['done'],      'border-t-emerald-500', 'text-emerald-600'],
            $summary['rating'] !== null
                ? ['Average rating', number_format($summary['rating'], 1) . ' / 5', 'border-t-violet-500', 'text-violet-600']
                : ['Cancelled', $summary['cancelled'], 'border-t-rose-400', 'text-rose-600'],
        ];

        // Label, value (null shows a dash), optional note
        $details = [
            ['Full name',       $user->name],
            ['Username',        $user->username ? '@' . $user->username : null],
            ['Email',           $user->email],
            ['Company',         $user->company],
            ['Sign-in method',  $signIn],
            ['Email verified',  $user->email_verified_at ? $dateTime($user->email_verified_at) : 'Not verified'],
            ['Member since',    $dateTime($user->created_at)],
            ['Last updated',    $dateTime($user->updated_at)],
            ['Last sign-in',    $lastLogin ? $lastLogin->whenLabel() : 'Never signed in'],
            ['Last activity',   $lastSeen ? $lastSeen->diffForHumans() : null],
            ['Account ID',      '#' . $user->id],
        ];
    @endphp

    {{-- Back link --}}
    <a href="{{ route('users.index') }}" class="mb-3 inline-flex items-center gap-1.5 text-sm font-medium text-slate-500 transition hover:text-brand-700">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
        Back to users
    </a>

    {{-- Profile header --}}
    <section class="{{ $card }} mb-4 p-4 sm:p-5">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex min-w-0 items-center gap-4"
                 x-data="{
                    error: @js($errors->first('avatar')),
                    pick(e) {
                        const f = e.target.files[0];
                        this.error = '';
                        if (!f) return;
                        if (!['image/jpeg', 'image/png', 'image/webp'].includes(f.type)) { this.error = 'Choose a JPG, PNG or WebP image.'; e.target.value = ''; return; }
                        if (f.size > 2 * 1024 * 1024) { this.error = 'That photo is over 2 MB. Choose a smaller one.'; e.target.value = ''; return; }
                        this.$refs.photoForm.submit();
                    }
                 }">
                <div class="relative shrink-0">
                    <x-avatar :user="$user" size="h-16 w-16" text="text-xl" />
                    @if ($manageable)
                        <form x-ref="photoForm" method="POST" action="{{ route('users.avatar.update', $user) }}" enctype="multipart/form-data">
                            @csrf
                            <input x-ref="photoFile" type="file" name="avatar" accept="image/png,image/jpeg,image/webp" class="sr-only" tabindex="-1" aria-label="Choose a photo for {{ $user->name }}" @change="pick($event)">
                        </form>
                        <button type="button" @click="$refs.photoFile.click()" title="Change photo" aria-label="Change the photo of {{ $user->name }}"
                                class="absolute -bottom-1 -right-1 flex h-7 w-7 items-center justify-center rounded-full border-2 border-white bg-brand-800 text-white shadow transition hover:bg-brand-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2">
                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 8h3l1.5-2h7L17 8h3a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V9a1 1 0 0 1 1-1z"/><circle cx="12" cy="13.5" r="3.5"/></svg>
                        </button>
                    @endif
                </div>
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="truncate text-xl font-bold tracking-tight text-slate-900">{{ $user->name }}</h2>
                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset {{ $badge[$user->role] ?? $badge['user'] }}">
                            @if ($user->role === 'admin')
                                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! \App\Support\RoleTheme::SHIELD !!}</svg>
                            @endif
                            {{ $user->roleLabel() }}
                        </span>
                        @if ($user->email_verified_at)
                            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-200">
                                <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 13l4 4L19 7"/></svg>
                                Verified
                            </span>
                        @endif
                    </div>
                    <p class="mt-0.5 truncate text-sm text-slate-500">{{ $user->email }}@if ($user->company), {{ $user->company }}@endif</p>
                    <p class="mt-0.5 text-xs text-slate-400">Member since {{ $user->created_at?->format('M j, Y') }}</p>
                    <p x-show="error" x-cloak x-text="error" role="alert" class="mt-1 text-sm font-medium text-red-600"></p>
                </div>
            </div>

            @if ($manageable)
                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" x-data x-on:click="$dispatch('edit-user', @js($payload))"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-brand-800 px-3.5 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 20h4L19 9a2.8 2.8 0 0 0-4-4L4 16v4z"/><path d="m13.5 6.5 4 4"/></svg>
                        Edit user
                    </button>
                    <button type="button" x-data x-on:click="$dispatch('open-modal', 'reset-user')"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-white px-3.5 py-2 text-sm font-medium text-brand-700 shadow-sm ring-1 ring-brand-200 transition hover:bg-brand-50">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="8" cy="15" r="4"/><path d="M10.8 12.2 20 3M17 6l3 3M14 9l2 2"/></svg>
                        Reset password
                    </button>
                    @if ($user->avatar)
                        <form method="POST" action="{{ route('users.avatar.destroy', $user) }}" onsubmit="return confirm('Remove this profile photo?')">
                            @csrf @method('DELETE')
                            <button class="inline-flex items-center gap-1.5 rounded-lg bg-white px-3.5 py-2 text-sm font-medium text-slate-700 shadow-sm ring-1 ring-slate-200 transition hover:bg-slate-50">Remove photo</button>
                        </form>
                    @endif
                    @if ($user->id !== auth()->id())
                        <button type="button" x-data x-on:click="$dispatch('open-modal', 'delete-user')"
                                class="inline-flex items-center gap-1.5 rounded-lg bg-white px-3.5 py-2 text-sm font-medium text-rose-700 shadow-sm ring-1 ring-rose-200 transition hover:bg-rose-50">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 7h16M10 11v6M14 11v6M6 7l1 12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-12M9 7V4h6v3"/></svg>
                            Delete
                        </button>
                    @endif
                </div>
            @endif
        </div>
    </section>

    <div class="grid gap-4 xl:grid-cols-3">
        {{-- Left: every detail on the account --}}
        <section class="{{ $card }} self-start" aria-labelledby="details-title">
            <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-4 py-3">
                <h3 id="details-title" class="font-semibold text-brand-800">Account details</h3>
                @if ($manageable)
                    <button type="button" x-data x-on:click="$dispatch('edit-user', @js($payload))" class="text-xs font-semibold text-brand-600 hover:underline">Edit details</button>
                @endif
            </div>
            <dl class="divide-y divide-slate-100">
                @foreach ($details as [$label, $value])
                    <div class="flex items-start justify-between gap-4 px-4 py-2.5 text-sm">
                        <dt class="shrink-0 text-slate-500">{{ $label }}</dt>
                        <dd class="min-w-0 break-words text-right font-medium {{ $value ? 'text-slate-800' : 'text-slate-300' }}">{{ $value ?: '-' }}</dd>
                    </div>
                @endforeach
                @if ($failed7d > 0)
                    <div class="flex items-start justify-between gap-4 bg-red-50/60 px-4 py-2.5 text-sm">
                        <dt class="shrink-0 text-red-700">Failed sign-ins (7 days)</dt>
                        <dd class="font-semibold text-red-700">{{ $failed7d }}</dd>
                    </div>
                @endif
            </dl>
        </section>

        {{-- Right: ticket summary, recent tickets, recent activity --}}
        <div class="space-y-4 xl:col-span-2">
            <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                @foreach ($tiles as [$label, $value, $border, $color])
                    <div class="rounded-xl border border-t-2 border-slate-200 {{ $border }} bg-white px-4 py-3 shadow-sm">
                        <p class="text-xs font-medium text-slate-500">{{ $label }}</p>
                        <p class="mt-1 text-2xl font-bold tracking-tight {{ $color }}">{{ $value }}</p>
                    </div>
                @endforeach
            </div>

            {{-- Recent tickets --}}
            <section class="{{ $card }} overflow-hidden" aria-labelledby="tickets-title">
                <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-4 py-3">
                    <div>
                        <h3 id="tickets-title" class="font-semibold text-brand-800">{{ $ticketsOf }}</h3>
                        <p class="text-xs text-slate-500">Latest {{ $recent->count() }} of {{ $summary['total'] }}</p>
                    </div>
                    @if ($user->role === 'user')
                        <a href="{{ route('tickets.index', ['search' => $user->name]) }}" class="shrink-0 text-xs font-medium text-brand-600 hover:underline">View in all tickets</a>
                    @endif
                </div>

                @if ($recent->isEmpty())
                    <div class="px-4 py-8 text-center">
                        <svg class="mx-auto h-8 w-8 text-slate-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 12h6M9 16h6M7 3h7l5 5v13H7z"/></svg>
                        <p class="mt-2 text-sm font-medium text-slate-700">No tickets yet</p>
                        <p class="text-sm text-slate-500">Tickets will appear here as soon as there are some.</p>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="border-b border-slate-200 bg-slate-50/80 text-left text-xs font-semibold text-slate-600">
                                <tr>
                                    <th scope="col" class="py-2.5 pl-4 pr-3">Ticket</th>
                                    @if ($user->role !== 'user') <th scope="col" class="hidden px-3 py-2.5 md:table-cell">Requester</th> @endif
                                    <th scope="col" class="px-3 py-2.5">Priority</th>
                                    <th scope="col" class="px-3 py-2.5">Status</th>
                                    <th scope="col" class="hidden px-3 py-2.5 text-right sm:table-cell">Created</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($recent as $t)
                                    <tr x-data @click="if (! $event.target.closest('a')) window.location = '{{ route('tickets.show', $t) }}'" class="cursor-pointer transition hover:bg-brand-50/40">
                                        <td class="max-w-xs py-2.5 pl-4 pr-3">
                                            <a href="{{ route('tickets.show', $t) }}" class="line-clamp-1 font-medium text-brand-700 hover:underline">{{ $t->subject }}</a>
                                            <p class="font-mono text-[11px] text-slate-400">{{ $t->ticket_no }}</p>
                                        </td>
                                        @if ($user->role !== 'user') <td class="hidden px-3 py-2.5 text-slate-600 md:table-cell">{{ $t->user->name }}</td> @endif
                                        <td class="px-3 py-2.5"><x-ticket-pill :ticket="$t" type="priority" /></td>
                                        <td class="px-3 py-2.5"><x-ticket-pill :ticket="$t" /></td>
                                        <td class="hidden whitespace-nowrap px-3 py-2.5 text-right text-xs text-slate-500 sm:table-cell" title="{{ $t->created_at->format('M d, Y h:i A') }}">{{ $t->created_at->diffForHumans() }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            {{-- Recent activity --}}
            <section class="{{ $card }} overflow-hidden" aria-labelledby="activity-title">
                <div class="border-b border-slate-100 px-4 py-3">
                    <h3 id="activity-title" class="font-semibold text-brand-800">Recent activity</h3>
                    <p class="text-xs text-slate-500">Sign-ins, security changes and ticket actions, newest first</p>
                </div>
                <ul class="divide-y divide-slate-100">
                    @forelse ($activity as $log)
                        @php([$class, $icon] = $log->look())
                        <li class="flex items-center gap-3 px-4 py-2.5">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full {{ $class }}">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $icon !!}</svg>
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium {{ $log->action === 'login_failed' ? 'text-red-700' : 'text-slate-800' }}">{{ $log->description }}</p>
                                <p class="truncate text-xs text-slate-500">{{ $log->whenLabel() }}@if ($log->device()), {{ $log->device() }}@endif @if ($log->ip_address), {{ $log->ip_address }}@endif</p>
                            </div>
                        </li>
                    @empty
                        <li class="px-4 py-8 text-center">
                            <p class="text-sm font-medium text-slate-700">No activity yet</p>
                            <p class="text-sm text-slate-500">Their sign-ins and actions will be listed here.</p>
                        </li>
                    @endforelse
                </ul>
            </section>
        </div>
    </div>

    @if ($manageable)
        @include('users._edit-modal')

        {{-- Reset password --}}
        <x-modal name="reset-user" maxWidth="md">
            <form method="POST" action="{{ route('users.reset-password', $user) }}" class="p-6">
                @csrf
                <div class="flex items-start gap-4">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-brand-50 text-brand-600">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="8" cy="15" r="4"/><path d="M10.8 12.2 20 3M17 6l3 3M14 9l2 2"/></svg>
                    </span>
                    <div>
                        <h3 class="text-base font-semibold text-slate-900">Reset this password?</h3>
                        <p class="mt-1 text-sm font-medium text-slate-600">{{ $user->name }}</p>
                        <p class="mt-2 text-sm text-slate-500">Their password will be set back to the default <code class="rounded bg-slate-100 px-1.5 py-0.5 text-slate-700">{{ \App\Http\Controllers\UserController::DEFAULT_PASSWORD }}</code> and they will be notified.</p>
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" x-on:click="$dispatch('close')" class="rounded-lg bg-white px-4 py-2 text-sm font-medium text-slate-700 ring-1 ring-slate-300 hover:bg-slate-50">Cancel</button>
                    <button type="submit" class="rounded-lg bg-brand-800 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">Yes, reset password</button>
                </div>
            </form>
        </x-modal>

        {{-- Delete --}}
        @if ($user->id !== auth()->id())
            <x-modal name="delete-user" maxWidth="md">
                <form method="POST" action="{{ route('users.destroy', $user) }}" class="p-6">
                    @csrf @method('DELETE')
                    <div class="flex items-start gap-4">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-rose-50 text-rose-600">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.3 3.9 2.4 18a2 2 0 0 0 1.7 3h15.8a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/></svg>
                        </span>
                        <div>
                            <h3 class="text-base font-semibold text-slate-900">Delete this user?</h3>
                            <p class="mt-1 text-sm font-medium text-slate-600">{{ $user->name }}</p>
                            <p class="mt-2 text-sm text-slate-500">The account and every ticket they submitted will be permanently removed.</p>
                        </div>
                    </div>
                    <div class="mt-6 flex justify-end gap-2">
                        <button type="button" x-on:click="$dispatch('close')" class="rounded-lg bg-white px-4 py-2 text-sm font-medium text-slate-700 ring-1 ring-slate-300 hover:bg-slate-50">Cancel</button>
                        <button type="submit" class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-700">Yes, delete</button>
                    </div>
                </form>
            </x-modal>
        @endif
    @endif
</x-app-layout>
