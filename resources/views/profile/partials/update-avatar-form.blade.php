<section x-data="{
    preview: null, fileName: '', fileSize: '', error: @js($errors->first('avatar')), confirmRemove: false,
    pick(e) {
        const f = e.target.files[0];
        this.error = '';
        if (!f) return this.clear();
        if (!['image/jpeg', 'image/png', 'image/webp'].includes(f.type)) { this.clear(); this.error = 'That file is not supported. Choose a JPG, PNG or WebP image.'; return; }
        if (f.size > 2 * 1024 * 1024) { this.clear(); this.error = 'That photo is too large (' + (f.size / 1048576).toFixed(1) + ' MB). Choose one under 2 MB.'; return; }
        this.preview = URL.createObjectURL(f);
        this.fileName = f.name;
        this.fileSize = f.size >= 1048576 ? (f.size / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(f.size / 1024)) + ' KB';
    },
    clear() { this.preview = null; this.fileName = ''; this.fileSize = ''; this.$refs.file.value = ''; },
}">
    <header>
        <h2 class="text-lg font-semibold text-brand-800">Profile photo</h2>
        <p class="mt-1 text-sm text-slate-500">Your photo appears next to your name on tickets and replies, so the team knows who they are helping.</p>
    </header>

    <div class="mt-6 flex flex-col items-center gap-6 sm:flex-row sm:items-center">
        {{-- Photo + camera button --}}
        <div class="relative shrink-0">
            <template x-if="preview">
                <img :src="preview" alt="Preview of the selected photo" class="h-28 w-28 rounded-full object-cover ring-4 ring-brand-100">
            </template>
            <div x-show="!preview">
                <x-avatar :user="$user" size="h-28 w-28" text="text-4xl" class="!ring-4 !ring-brand-100" />
            </div>
            <button type="button" @click="$refs.file.click()" aria-label="Choose a new photo"
                    class="absolute -bottom-1 -right-1 flex h-9 w-9 items-center justify-center rounded-full border-2 border-white bg-brand-800 text-white shadow transition hover:bg-brand-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2">
                <svg class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 8h3l1.5-2h7L17 8h3a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V9a1 1 0 0 1 1-1z"/><circle cx="12" cy="13.5" r="3.5"/></svg>
            </button>
        </div>

        <div class="min-w-0 flex-1 text-center sm:text-left">
            <form id="avatar-form" method="POST" action="{{ route('profile.avatar.update') }}" enctype="multipart/form-data">
                @csrf
                <input x-ref="file" type="file" name="avatar" accept="image/png,image/jpeg,image/webp" class="sr-only" tabindex="-1" aria-label="Choose a photo" @change="pick($event)">
            </form>
            @if ($user->avatar)
                <form id="avatar-remove-form" method="POST" action="{{ route('profile.avatar.destroy') }}">
                    @csrf @method('DELETE')
                </form>
            @endif

            {{-- Nothing chosen yet --}}
            <div x-show="!preview && !confirmRemove">
                <p class="text-sm text-slate-500">JPG, PNG or WebP, up to 2 MB. A square photo with your face centered works best.</p>
                <div class="mt-4 flex flex-wrap items-center justify-center gap-3 sm:justify-start">
                    <button type="button" @click="$refs.file.click()"
                            class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 16V4M7 9l5-5 5 5M5 20h14"/></svg>
                        {{ $user->avatar ? 'Upload a new photo' : 'Upload a photo' }}
                    </button>
                    @if ($user->avatar)
                        <button type="button" @click="confirmRemove = true" class="rounded-lg px-3 py-2.5 text-sm font-semibold text-red-600 transition hover:bg-red-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500">Remove photo</button>
                    @endif
                </div>
            </div>

            {{-- Photo chosen: confirm or cancel --}}
            <div x-show="preview" x-cloak>
                <p class="text-sm font-semibold text-slate-800">Ready to save</p>
                <p class="mt-0.5 truncate text-sm text-slate-500"><span x-text="fileName"></span> <span class="text-slate-400" x-text="'(' + fileSize + ')'"></span></p>
                <div class="mt-4 flex flex-wrap items-center justify-center gap-3 sm:justify-start">
                    <button type="submit" form="avatar-form" class="rounded-lg bg-brand-800 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2">Save photo</button>
                    <button type="button" @click="clear()" class="rounded-lg px-3 py-2.5 text-sm font-semibold text-slate-600 transition hover:bg-slate-100">Cancel</button>
                </div>
            </div>

            @if ($user->avatar)
                {{-- Remove: ask once, inline --}}
                <div x-show="confirmRemove && !preview" x-cloak role="alertdialog" aria-label="Remove profile photo">
                    <p class="text-sm font-semibold text-slate-800">Remove your profile photo?</p>
                    <p class="mt-0.5 text-sm text-slate-500">Your initials will be shown instead.</p>
                    <div class="mt-4 flex flex-wrap items-center justify-center gap-3 sm:justify-start">
                        <button type="submit" form="avatar-remove-form" class="rounded-lg bg-red-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:ring-offset-2">Yes, remove it</button>
                        <button type="button" @click="confirmRemove = false" class="rounded-lg px-3 py-2.5 text-sm font-semibold text-slate-600 transition hover:bg-slate-100">Keep photo</button>
                    </div>
                </div>

            @endif

            <p x-show="error" x-cloak x-text="error" role="alert" class="mt-3 flex items-start justify-center gap-2 text-sm font-medium text-red-600 sm:justify-start"></p>
        </div>
    </div>
</section>
