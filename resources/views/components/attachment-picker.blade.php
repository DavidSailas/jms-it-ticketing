{{--
    File picker for screenshots, error photos and log files.
    - click to choose, drag files onto it, or paste a screenshot (Ctrl+V) while typing in the same form
    - chosen files are listed and can be removed one by one before sending
    The slot is the hint line under the title (the ticket form changes it with the category).
--}}
@props(['label' => 'Attach screenshots or logs'])
@php
    $max = \App\Support\Attachments::MAX_FILES;
    $mb  = \App\Support\Attachments::MAX_MB;
@endphp

<div x-data="{
        max: {{ $max }}, maxBytes: {{ $mb * 1048576 }}, files: [], problem: '', over: false,
        add(list) {
            this.problem = '';
            for (const f of Array.from(list)) {
                if (this.files.length >= this.max) { this.problem = 'You can attach up to ' + this.max + ' files at a time.'; break; }
                if (f.size > this.maxBytes) { this.problem = f.name + ' is larger than {{ $mb }} MB.'; continue; }
                this.files.push(f);
            }
            this.sync();
        },
        remove(i) { this.files.splice(i, 1); this.problem = ''; this.sync(); },
        sync() {
            const dt = new DataTransfer();
            this.files.forEach(f => dt.items.add(f));
            this.$refs.input.files = dt.files;
        },
        size(b) { return b >= 1048576 ? (b / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(b / 1024)) + ' KB'; },
        pasted(e) {
            const got = Array.from((e.clipboardData && e.clipboardData.files) || []);
            if (!got.length) return;
            e.preventDefault();
            this.add(got.map(f => f.name === 'image.png' ? new File([f], 'screenshot-' + Date.now() + '.png', { type: f.type }) : f));
        }
    }"
    x-init="$el.closest('form') && $el.closest('form').addEventListener('paste', e => pasted(e))"
    {{ $attributes }}>

    <span class="mb-1.5 block text-sm font-medium text-slate-700">{{ $label }} <span class="font-normal text-slate-400">(optional)</span></span>

    <label class="flex cursor-pointer flex-col items-center gap-1 rounded-xl border-2 border-dashed px-4 py-5 text-center transition focus-within:ring-2 focus-within:ring-brand-500"
           :class="over ? 'border-brand-500 bg-brand-50' : 'border-slate-300 bg-slate-50 hover:border-brand-400 hover:bg-brand-50/50'"
           @dragover.prevent="over = true" @dragleave.prevent="over = false" @drop.prevent="over = false; add($event.dataTransfer.files)">
        <input x-ref="input" type="file" name="files[]" multiple accept="{{ \App\Support\Attachments::accept() }}" class="sr-only" @change="add($event.target.files)">
        <svg class="h-6 w-6 text-brand-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.5 12.7 20.8a5.5 5.5 0 0 1-7.8-7.8l8.5-8.5a3.7 3.7 0 0 1 5.2 5.2l-8.5 8.5a1.8 1.8 0 0 1-2.6-2.6l7.8-7.8"/></svg>
        <span class="text-sm font-medium text-slate-700">Choose files, drop them here, or paste a screenshot</span>
        <span class="text-xs text-slate-500">{{ \App\Support\Attachments::label() }} &middot; up to {{ $max }} files, {{ $mb }} MB each</span>
    </label>

    @if (trim((string) $slot) !== '')
        <p class="mt-1.5 text-xs text-slate-500">{{ $slot }}</p>
    @endif
    <p class="mt-1 text-xs text-slate-400">Please do not attach files that show passwords.</p>

    <ul x-show="files.length" x-cloak class="mt-3 space-y-1.5">
        <template x-for="(f, i) in files" :key="i + f.name + f.size">
            <li class="flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">
                <svg class="h-4 w-4 shrink-0 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M7 3h8l4 4v14H7z"/><path d="M15 3v4h4"/></svg>
                <span class="min-w-0 flex-1 truncate text-slate-700" x-text="f.name"></span>
                <span class="shrink-0 text-xs text-slate-400" x-text="size(f.size)"></span>
                <button type="button" @click="remove(i)" class="shrink-0 rounded p-1 text-slate-400 hover:bg-red-50 hover:text-red-600 focus:outline-none focus:ring-2 focus:ring-red-400" :aria-label="'Remove ' + f.name">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg>
                </button>
            </li>
        </template>
    </ul>
    <p x-show="problem" x-cloak x-text="problem" role="alert" class="mt-1.5 text-sm text-red-600"></p>

    @foreach (collect($errors->get('files'))->merge(collect($errors->get('files.*'))->flatten())->unique() as $message)
        <p role="alert" class="mt-1.5 text-sm text-red-600">{{ $message }}</p>
    @endforeach
</div>
