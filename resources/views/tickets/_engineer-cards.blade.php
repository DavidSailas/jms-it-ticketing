{{--
    Grouped engineer cards (JMS support team / company IT team), shared by the ticket page and the quick-assign panel.
    Needs: $engineers, $workload. Optional: $ticket (server-side selection), $xmodel (Alpine variable holding the chosen id).
--}}
@php($xmodel = $xmodel ?? null)
@php($ticket = $ticket ?? null)
@php($groups = $engineers->sortBy(fn ($e) => [$workload[$e->id] ?? 0, $e->name])->groupBy(fn ($e) => $e->isJmsEngineer() ? 'jms' : 'company'))
@php($selected = $xmodel ? null : (int) old('assigned_to', $ticket?->assigned_to))
<div class="space-y-4" role="radiogroup" aria-label="IT engineer">
    @foreach (['jms' => 'JMS support team', 'company' => ($ticket?->company?->name ?? 'Company') . ' IT team'] as $key => $title)
        @continue(! isset($groups[$key]))
        <div>
            <div class="mb-2 flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $title }}</span>
                <span class="rounded-full {{ $key === 'jms' ? 'bg-brand-50 text-brand-700' : 'bg-slate-100 text-slate-600' }} px-2 py-0.5 text-[11px] font-medium">
                    {{ $key === 'jms' ? 'Our team' : 'Partner' }} &middot; {{ $groups[$key]->count() }}
                </span>
            </div>
            <div class="grid gap-2 sm:grid-cols-2">
                @foreach ($groups[$key] as $e)
                    @php($n = $workload[$e->id] ?? 0)
                    <label class="cursor-pointer">
                        <input type="radio" name="assigned_to" value="{{ $e->id }}" class="peer sr-only" required
                               @if ($xmodel) x-model="{{ $xmodel }}" @else @checked($selected === $e->id) @endif>
                        <span class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white p-3 transition hover:border-slate-300 peer-checked:border-brand-600 peer-checked:bg-brand-50 peer-checked:ring-2 peer-checked:ring-brand-100 peer-focus-visible:ring-2 peer-focus-visible:ring-brand-500">
                            @if ($e->avatarUrl())
                                <img src="{{ $e->avatarUrl() }}" alt="" class="h-10 w-10 shrink-0 rounded-full object-cover">
                            @else
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-brand-100 text-sm font-semibold text-brand-700">{{ $e->initials() }}</span>
                            @endif
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-medium text-slate-800">{{ $e->name }}</span>
                                <span class="mt-0.5 flex flex-wrap items-center gap-1.5">
                                    @if ($n === 0)
                                        <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-medium text-emerald-700">Available</span>
                                    @elseif ($n >= 4)
                                        <span class="rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-medium text-amber-700">Busy &middot; {{ $n }} active</span>
                                    @else
                                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-600">{{ $n }} active</span>
                                    @endif
                                    @if ($xmodel)
                                        <span x-show="Number(t.assigned_to) === {{ $e->id }}" x-cloak class="rounded-full bg-violet-50 px-2 py-0.5 text-[11px] font-medium text-violet-700">Current</span>
                                    @elseif ((int) $ticket?->assigned_to === $e->id)
                                        <span class="rounded-full bg-violet-50 px-2 py-0.5 text-[11px] font-medium text-violet-700">Current</span>
                                    @endif
                                </span>
                            </span>
                        </span>
                    </label>
                @endforeach
            </div>
        </div>
    @endforeach
</div>
