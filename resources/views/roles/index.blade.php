<x-app-layout>
    <x-slot name="header">Roles &amp; permissions</x-slot>

    @php
        $card  = 'rounded-2xl border border-slate-200 bg-white shadow-sm';
        $tick  = '<svg class="mx-auto h-4 w-4 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-label="Allowed"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12 5 5L20 7"/></svg>';
        $dash  = '<span class="text-slate-300" aria-label="Not allowed">&ndash;</span>';
        $allow = fn ($role, $key) => $role->slug === 'super_admin' || in_array($key, $role->permissions ?? [], true);
    @endphp

    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h2 class="text-xl font-extrabold tracking-tight text-brand-800">Who can do what</h2>
            <p class="mt-1 max-w-2xl text-sm text-slate-500">A role is a named set of permissions. Create a role, tick what it may do, then add people to it. Super Admin always has full access.</p>
        </div>
        <a href="{{ route('roles.create') }}" class="inline-flex shrink-0 items-center justify-center gap-2 rounded-lg bg-brand-800 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-brand-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
            New role
        </a>
    </div>

    {{-- Role cards --}}
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach ($roles as $role)
            @php
                $granted = $role->slug === 'super_admin' ? array_keys($catalog) : ($role->permissions ?? []);
                $total   = count(\App\Support\Permissions::forBase($role->base_role));
            @endphp
            <article class="{{ $card }} flex flex-col p-5">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h3 class="truncate text-base font-semibold text-slate-900">{{ $role->name }}</h3>
                        <p class="mt-0.5 text-xs text-slate-500">{{ $role->baseLabel() }}</p>
                    </div>
                    @if ($role->is_system)
                        <span class="shrink-0 rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-semibold text-slate-600">Built-in</span>
                    @else
                        <span class="shrink-0 rounded-full bg-violet-50 px-2.5 py-0.5 text-[11px] font-semibold text-violet-700">Custom</span>
                    @endif
                </div>

                @if ($role->description)
                    <p class="mt-3 text-sm text-slate-600">{{ $role->description }}</p>
                @endif

                <div class="mt-3 flex flex-wrap gap-1.5">
                    @forelse (array_slice($granted, 0, 4) as $key)
                        <span class="rounded-full bg-brand-50 px-2 py-0.5 text-[11px] font-medium text-brand-700">{{ $catalog[$key]['label'] ?? $key }}</span>
                    @empty
                        <span class="text-xs text-slate-400">No extra permissions</span>
                    @endforelse
                    @if (count($granted) > 4)
                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-600">+{{ count($granted) - 4 }} more</span>
                    @endif
                </div>

                <div class="mt-auto flex items-center justify-between gap-2 pt-4">
                    <p class="text-xs text-slate-500">
                        {{ $role->slug === 'super_admin' ? 'Everything' : count($granted) . ' of ' . $total . ' permissions' }}
                        &middot; {{ $role->members_count }} {{ \Illuminate\Support\Str::plural('person', $role->members_count) }}
                    </p>
                    <div class="flex items-center gap-1.5">
                        <a href="{{ route('roles.edit', $role) }}" class="rounded-lg px-3 py-1.5 text-sm font-medium text-slate-700 ring-1 ring-slate-300 hover:bg-slate-50">{{ $role->isLocked() ? 'View' : 'Edit' }}</a>
                        @unless ($role->is_system)
                            <form method="POST" action="{{ route('roles.destroy', $role) }}" onsubmit="return confirm('Delete the role {{ addslashes($role->name) }}? This cannot be undone.')">
                                @csrf @method('DELETE')
                                <button class="rounded-lg px-3 py-1.5 text-sm font-medium text-rose-700 ring-1 ring-rose-200 hover:bg-rose-50" @disabled($role->members_count > 0) title="{{ $role->members_count > 0 ? 'Remove its people first' : 'Delete role' }}">Delete</button>
                            </form>
                        @endunless
                    </div>
                </div>
            </article>
        @endforeach
    </div>

    {{-- Side-by-side comparison --}}
    <section class="{{ $card }} mt-8 overflow-hidden" aria-labelledby="compare-heading">
        <div class="px-5 pt-5">
            <h3 id="compare-heading" class="text-sm font-semibold uppercase tracking-wide text-slate-500">Compare roles</h3>
        </div>
        <div class="mt-3 overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th scope="col" class="px-4 py-3 text-left">Permission</th>
                        @foreach ($roles as $role)
                            <th scope="col" class="whitespace-nowrap px-3 py-3 text-center">{{ $role->name }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach (\App\Support\Permissions::GROUPS as $group => $items)
                        <tr class="bg-slate-50/60"><th colspan="{{ $roles->count() + 1 }}" class="px-4 py-2 text-left text-[11px] font-bold uppercase tracking-wide text-slate-500">{{ $group }}</th></tr>
                        @foreach ($items as $key => [$label])
                            <tr class="hover:bg-slate-50/60">
                                <th scope="row" class="whitespace-nowrap px-4 py-2.5 text-left font-medium text-slate-700">{{ $label }}</th>
                                @foreach ($roles as $role)
                                    <td class="px-3 py-2.5 text-center">{!! $allow($role, $key) ? $tick : $dash !!}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="px-5 py-3 text-xs text-slate-500">Admin-level roles see every ticket and partner. IT Engineer-level roles only see the tickets assigned to them. Customers only see their own tickets. Managing roles is always limited to Super Admins.</p>
    </section>
</x-app-layout>
