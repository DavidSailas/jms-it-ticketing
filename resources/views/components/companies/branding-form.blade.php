{{-- A company's name (optional), logo and colour, with a live preview. Used on the admin's Branding page and on a company's page for JMS. --}}
@props(['company', 'action', 'showName' => false])

@php
    $ok  = 'border-slate-300 focus:border-brand-500 focus:ring-brand-500';
    $bad = 'border-red-400 focus:border-red-500 focus:ring-red-500';
    $default = \App\Support\Branding::DEFAULT;
    $presets = [
        ['Standard blue', '#164a99'], ['Ocean', '#0369a1'], ['Teal', '#0f766e'], ['Forest', '#047857'],
        ['Violet', '#6d28d9'], ['Rose', '#be123c'], ['Orange', '#c2410c'], ['Slate', '#334155'],
    ];
    $id = 'brand-' . $company->id;
@endphp

<form method="POST" action="{{ $action }}" enctype="multipart/form-data" novalidate
      x-data="brandingForm(@js([
          'name'     => old('name', $company->name),
          'color'    => strtolower(old('brand_color', $company->brand_color ?: $default)),
          'logo'     => $company->logoUrl(),
          'hasLogo'  => (bool) $company->logo_path,
          'fallback' => asset('images/logo.png'),
          'default'  => $default,
      ]))"
      @submit="loading = true"
      class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
    @csrf @method('PATCH')

    <div class="border-b border-slate-100 px-5 py-4">
        <h3 class="font-semibold text-brand-800">Branding</h3>
        <p class="mt-0.5 text-sm text-slate-500">Your logo, name and colour appear on every page your people see.</p>
    </div>

    {{-- Live preview of the sidebar and a button, exactly as people will see them --}}
    <div class="border-b border-slate-100 bg-slate-50 px-5 py-5">
        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">Live preview</p>
        <div class="flex overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="w-44 shrink-0 border-r border-slate-100 p-3 sm:w-52">
                <div class="flex justify-center">
                    <template x-if="shownLogo">
                        <img :src="shownLogo" alt="" class="max-h-14 w-auto max-w-[8rem] object-contain">
                    </template>
                    <template x-if="!shownLogo">
                        <img :src="fallback" alt="" class="h-16 object-contain">
                    </template>
                </div>
                <p class="mt-2 truncate text-center text-sm font-bold" :style="`color:${color}`" x-text="name || 'Company name'"></p>
                <div class="mt-3 space-y-1 text-xs font-medium">
                    <div class="rounded-md px-2.5 py-1.5" :style="`color:${color};background:${color}1a`">Dashboard</div>
                    <div class="px-2.5 py-1.5 text-slate-500">All Tickets</div>
                    <div class="px-2.5 py-1.5 text-slate-500">Schedule</div>
                </div>
            </div>
            <div class="flex min-w-0 flex-1 flex-col justify-center gap-3 p-4">
                <p class="text-sm font-semibold" :style="`color:${color}`">Submit a new ticket</p>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="rounded-lg px-3 py-1.5 text-xs font-semibold text-white shadow-sm" :style="`background:${color}`">New ticket</span>
                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold" :style="`color:${color};background:${color}1a`">In progress</span>
                </div>
            </div>
        </div>
    </div>

    <div class="space-y-6 px-5 py-5">
        @if ($showName)
            <div>
                <label for="{{ $id }}-name" class="mb-1 block text-sm font-medium text-slate-700">Company name</label>
                <input id="{{ $id }}-name" name="name" x-model="name" required class="w-full rounded-lg {{ $errors->has('name') ? $bad : $ok }}">
                @error('name') <p role="alert" class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        @endif

        {{-- Logo --}}
        <div>
            <label for="{{ $id }}-logo" class="mb-1 block text-sm font-medium text-slate-700">Logo</label>

            <div x-on:dragover.prevent="drag = true" x-on:dragleave.prevent="drag = false" x-on:drop.prevent="dropFile($event)"
                 :class="drag ? 'border-brand-500 bg-brand-50' : 'border-slate-300 bg-slate-50 hover:border-slate-400'"
                 class="flex flex-col items-center gap-3 rounded-xl border-2 border-dashed px-4 py-5 text-center transition sm:flex-row sm:text-left">
                <div class="flex h-16 w-24 shrink-0 items-center justify-center rounded-lg bg-white ring-1 ring-slate-200">
                    <template x-if="shownLogo"><img :src="shownLogo" alt="Current logo" class="max-h-12 max-w-[5rem] object-contain"></template>
                    <template x-if="!shownLogo"><svg class="h-7 w-7 text-slate-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="9" cy="9" r="1.5"/><path d="m21 15-4.5-4.5L6 21"/></svg></template>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-medium text-slate-700"><span x-text="fileName || (shownLogo ? 'Drop a new image to replace it' : 'Drop your logo here')"></span></p>
                    <p class="mt-0.5 text-xs text-slate-500">PNG, JPG or WebP, up to 2 MB. A plain white or grey background is removed and the edges are trimmed for you (after you save).</p>
                </div>
                <div class="flex shrink-0 gap-2">
                    <button type="button" x-on:click="$refs.file.click()" class="rounded-lg bg-white px-3.5 py-2 text-sm font-semibold text-slate-700 shadow-sm ring-1 ring-slate-300 hover:bg-slate-50">
                        <span x-text="shownLogo ? 'Change logo' : 'Choose file'"></span>
                    </button>
                    <button type="button" x-show="hasLogo && !removeLogo || fileName" x-cloak x-on:click="clearLogo()" class="rounded-lg px-3 py-2 text-sm font-medium text-rose-600 hover:bg-rose-50">Remove</button>
                </div>
            </div>

            <input id="{{ $id }}-logo" x-ref="file" type="file" name="logo" accept="image/png,image/jpeg,image/webp" x-on:change="pickLogo($event.target.files[0])" class="sr-only">
            <input type="checkbox" name="remove_logo" value="1" x-model="removeLogo" class="hidden" tabindex="-1" aria-hidden="true">

            <p x-show="logoError" x-cloak x-text="logoError" role="alert" class="mt-1.5 text-sm text-red-600"></p>
            @error('logo') <p role="alert" class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
            <p class="mt-2 text-xs text-slate-500">Tip: a transparent PNG at least 400 px wide looks sharpest.</p>
        </div>

        {{-- Colour --}}
        <div>
            <label class="mb-2 block text-sm font-medium text-slate-700">Brand colour</label>
            <div class="flex flex-wrap items-center gap-2" role="radiogroup" aria-label="Preset colours">
                @foreach ($presets as [$label, $hex])
                    <button type="button" role="radio" :aria-checked="color === '{{ $hex }}'" title="{{ $label }}" aria-label="{{ $label }}"
                            x-on:click="color = '{{ $hex }}'"
                            :class="color === '{{ $hex }}' ? 'ring-2 ring-offset-2 ring-slate-800' : 'ring-1 ring-slate-200 hover:scale-110'"
                            class="h-8 w-8 rounded-full transition" style="background: {{ $hex }}"></button>
                @endforeach
                <span class="mx-1 h-6 w-px bg-slate-200" aria-hidden="true"></span>
                <label class="flex cursor-pointer items-center gap-2 rounded-lg px-2 py-1 text-sm font-medium text-slate-600 hover:bg-slate-100">
                    <input type="color" name="brand_color" x-model="color" class="h-8 w-9 cursor-pointer rounded border border-slate-300 bg-white p-0.5" aria-label="Pick any colour">
                    Custom
                </label>
                <code class="rounded bg-slate-100 px-2 py-1 text-xs text-slate-600" x-text="color"></code>
            </div>
            <p x-show="tooLight" x-cloak role="alert" class="mt-2 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800">
                This colour is too light: white text on buttons would be hard to read. Choose a darker one.
            </p>
            @error('brand_color') <p role="alert" class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
            <p class="mt-2 text-xs text-slate-500">Used for buttons, headings and highlights.</p>
        </div>
    </div>

    <div class="flex flex-col-reverse items-stretch gap-2 border-t border-slate-100 bg-slate-50/60 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
        <button type="button" x-show="dirty" x-cloak x-on:click="reset()" class="text-left text-sm font-medium text-slate-500 hover:text-slate-700">Undo changes</button>
        <span x-show="!dirty" class="text-sm text-slate-400">No changes yet</span>
        <button type="submit" :disabled="loading || !dirty || tooLight || !!logoError"
                class="rounded-lg bg-brand-800 px-5 py-2 text-sm font-semibold text-white hover:bg-brand-700 disabled:cursor-not-allowed disabled:opacity-50">
            <span x-text="loading ? 'Saving...' : 'Save branding'"></span>
        </button>
    </div>
</form>

@once
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('brandingForm', (start) => ({
                ...start,
                original: { name: start.name, color: start.color },
                shownFile: null,
                fileName: '',
                logoError: '',
                removeLogo: false,
                drag: false,
                loading: false,

                get shownLogo() {
                    if (this.shownFile) return this.shownFile;
                    return this.removeLogo ? null : this.logo;
                },
                get dirty() {
                    return this.name !== this.original.name || this.color !== this.original.color || !!this.fileName || this.removeLogo;
                },
                get tooLight() {
                    const h = this.color.replace('#', '');
                    if (h.length !== 6) return false;
                    const lin = (v) => { v /= 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); };
                    const [r, g, b] = [0, 2, 4].map((i) => lin(parseInt(h.substr(i, 2), 16)));
                    return 1.05 / (0.2126 * r + 0.7152 * g + 0.0722 * b + 0.05) < 3.5;
                },

                dropFile(e) {
                    this.drag = false;
                    const f = e.dataTransfer.files[0];
                    if (!f) return;
                    const dt = new DataTransfer();
                    dt.items.add(f);
                    this.$refs.file.files = dt.files;
                    this.pickLogo(f);
                },
                pickLogo(f) {
                    this.logoError = '';
                    if (!f) return;
                    if (!['image/png', 'image/jpeg', 'image/webp'].includes(f.type)) {
                        this.logoError = 'Use a PNG, JPG or WebP image.';
                        return this.dropPick();
                    }
                    if (f.size > 2 * 1024 * 1024) {
                        this.logoError = 'This image is larger than 2 MB. Choose a smaller one.';
                        return this.dropPick();
                    }
                    if (this.shownFile) URL.revokeObjectURL(this.shownFile);
                    this.shownFile = URL.createObjectURL(f);
                    this.fileName = f.name;
                    this.removeLogo = false;
                },
                dropPick() {
                    this.$refs.file.value = '';
                    this.shownFile = null;
                    this.fileName = '';
                },
                clearLogo() {
                    this.logoError = '';
                    this.$refs.file.value = '';
                    this.shownFile = null;
                    this.fileName = '';
                    this.removeLogo = this.hasLogo;
                },
                reset() {
                    this.name = this.original.name;
                    this.color = this.original.color;
                    this.removeLogo = false;
                    this.logoError = '';
                    this.dropPick();
                },
            }));
        });
    </script>
@endonce
