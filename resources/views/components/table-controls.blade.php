@props(['paginator', 'options', 'default', 'noun' => 'result', 'embedded' => false])

{{-- Result count + "Sort by" + "Rows per page" for a paginated table. $options = [['value' => 'created_at|desc', 'label' => 'Newest first'], ...] --}}
@php
    $current = request('sort') ? request('sort') . '|' . (request('dir') === 'asc' ? 'asc' : 'desc') : $default;
    $perPage = $paginator->perPage();
    $keep    = collect(request()->except(['sort', 'dir', 'per_page', 'page']))->filter(fn ($v) => is_scalar($v));
@endphp

<form method="GET" class="{{ $embedded ? 'border-b border-slate-200 bg-white px-5 py-3' : 'mb-3' }} flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
    @foreach ($keep as $k => $v) <input type="hidden" name="{{ $k }}" value="{{ $v }}"> @endforeach
    <input type="hidden" name="sort" value="{{ explode('|', $current)[0] }}">
    <input type="hidden" name="dir" value="{{ explode('|', $current)[1] ?? 'desc' }}">

    <p class="text-sm text-slate-500">
        Showing <span class="font-semibold text-slate-800">{{ $paginator->firstItem() }}&ndash;{{ $paginator->lastItem() }}</span>
        of <span class="font-semibold text-slate-800">{{ number_format($paginator->total()) }}</span>
        {{ \Illuminate\Support\Str::plural($noun, $paginator->total()) }}
    </p>

    <div class="flex items-center gap-3 text-sm">
        <label class="flex items-center gap-2 text-slate-500">
            <span class="hidden sm:inline">Sort by</span>
            <select onchange="const [s, d] = this.value.split('|'); this.form.sort.value = s; this.form.dir.value = d; this.form.submit()"
                    class="rounded-lg border-slate-300 py-1.5 pl-3 pr-8 text-sm text-slate-700 shadow-sm focus:border-brand-500 focus:ring-brand-500" aria-label="Sort by">
                @foreach ($options as $o)
                    <option value="{{ $o['value'] }}" @selected($current === $o['value'])>{{ $o['label'] }}</option>
                @endforeach
            </select>
        </label>
        <label class="flex items-center gap-2 text-slate-500">
            <span class="hidden sm:inline">Rows</span>
            <select name="per_page" onchange="this.form.submit()" class="rounded-lg border-slate-300 py-1.5 pl-3 pr-8 text-sm text-slate-700 shadow-sm focus:border-brand-500 focus:ring-brand-500" aria-label="Rows per page">
                @foreach ([10, 25, 50] as $n)
                    <option value="{{ $n }}" @selected($perPage === $n)>{{ $n }}</option>
                @endforeach
            </select>
        </label>
    </div>
</form>
