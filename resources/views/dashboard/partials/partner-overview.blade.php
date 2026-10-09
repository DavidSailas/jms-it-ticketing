{{-- Super admin (JMS) only: one row per partner company, the ones that need a decision first. --}}
@php
    $shown = array_slice($partners, 0, 6);
    $state = [
        'attention' => ['Needs attention', 'bg-rose-50 text-rose-700 ring-rose-200'],
        'watch'     => ['Keep an eye', 'bg-amber-50 text-amber-700 ring-amber-200'],
        'ok'        => ['On track', 'bg-emerald-50 text-emerald-700 ring-emerald-200'],
    ];
    $cols = 'sm:grid-cols-[minmax(0,1fr)_repeat(4,4.25rem)_7.5rem]';
@endphp

<section class="{{ $card }} overflow-hidden" aria-labelledby="partners-title">
    <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-4 py-3">
        <div>
            <h3 id="partners-title" class="font-semibold text-brand-800">Partner companies</h3>
            <p class="text-xs text-slate-500">Who needs a decision first, most urgent at the top</p>
        </div>
        <a href="{{ route('companies.index') }}" class="shrink-0 text-sm font-medium text-brand-600 hover:underline">All companies</a>
    </div>

    @if (count($shown) === 0)
        <div class="px-4 py-8 text-center">
            <p class="text-sm font-medium text-slate-700">No partner companies yet</p>
            <p class="mt-0.5 text-sm text-slate-500">Add your first partner and their tickets will be tracked here.</p>
            <a href="{{ route('companies.index') }}" class="mt-3 inline-block rounded-lg bg-brand-800 px-3.5 py-2 text-sm font-semibold text-white transition hover:bg-brand-700">Add a company</a>
        </div>
    @else
        <div class="hidden border-b border-slate-100 bg-slate-50/60 px-4 py-2 text-[11px] font-semibold text-slate-500 sm:grid {{ $cols }} sm:gap-3" aria-hidden="true">
            <span>Company</span>
            <span class="text-center">Active</span>
            <span class="text-center">Overdue</span>
            <span class="text-center">Urgent</span>
            <span class="text-center">Done, 30d</span>
            <span>Status</span>
        </div>

        <ul class="divide-y divide-slate-100">
            @foreach ($shown as $p)
                @php
                    $c = $p['company'];
                    [$stateLabel, $stateClass] = $state[$p['state']];
                    $cells = [
                        ['Active', $p['active'], 'text-slate-900'],
                        ['Overdue', $p['overdue'], $p['overdue'] ? 'font-bold text-rose-600' : 'text-slate-300'],
                        ['Urgent', $p['urgent'], $p['urgent'] ? 'font-bold text-orange-600' : 'text-slate-300'],
                        ['Done, 30d', $p['resolved'], 'text-slate-900'],
                    ];
                @endphp
                <li>
                    <a href="{{ route('companies.show', $c) }}"
                       class="grid grid-cols-4 items-center gap-x-3 gap-y-2 px-4 py-3 transition hover:bg-brand-50/50 focus:outline-none focus-visible:bg-brand-50/50 {{ $cols }}">
                        <div class="col-span-4 flex min-w-0 items-center gap-3 sm:col-span-1">
                            @if ($c->logoUrl())
                                <img src="{{ $c->logoUrl() }}" alt="" loading="lazy" class="h-9 w-9 shrink-0 rounded-lg bg-white object-contain ring-1 ring-slate-200">
                            @else
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-brand-800 text-sm font-bold uppercase text-white"
                                      @if (\App\Support\Branding::isHex($c->brand_color)) style="background-color: {{ $c->brand_color }}" @endif>{{ \Illuminate\Support\Str::substr($c->name, 0, 1) }}</span>
                            @endif
                            <div class="min-w-0">
                                <p class="truncate font-semibold text-slate-900">{{ $c->name }}</p>
                                <p class="truncate text-xs text-slate-500">
                                    @if ($p['awaiting'])
                                        <span class="font-medium text-violet-700">{{ $p['awaiting'] }} waiting for acceptance</span>
                                    @else
                                        Nothing waiting
                                    @endif
                                    @if ($p['rating']) <span aria-hidden="true" class="px-1 text-slate-300">/</span>Rated {{ number_format($p['rating'], 1) }}/5 @endif
                                </p>
                            </div>
                        </div>

                        @foreach ($cells as [$label, $value, $class])
                            <div class="text-center">
                                <p class="text-base tabular-nums {{ $class }} {{ str_contains($class, 'font-bold') ? '' : 'font-semibold' }}">{{ $value }}</p>
                                <p class="text-[10px] text-slate-400 sm:hidden">{{ $label }}</p>
                            </div>
                        @endforeach

                        <div class="col-span-4 sm:col-span-1">
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium ring-1 ring-inset {{ $stateClass }}">{{ $stateLabel }}</span>
                        </div>
                    </a>
                </li>
            @endforeach
        </ul>

        @if (count($partners) > count($shown))
            <div class="border-t border-slate-100 bg-slate-50/50 px-4 py-2.5 text-sm text-slate-500">
                Showing {{ count($shown) }} of {{ count($partners) }} partners.
                <a href="{{ route('companies.index') }}" class="font-medium text-brand-600 hover:underline">See the rest</a>
            </div>
        @endif
    @endif
</section>
