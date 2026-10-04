@props(['roles', 'model', 'disabled' => 'false', 'error' => null])

{{-- Role chooser: one clear card per role, with a plain-language description of what the role can do. --}}
@php
    $meta = [
        'user'       => ['User',        'Submits tickets and follows their progress.',             '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>'],
        'it_support' => ['IT Support',  'Works on assigned tickets and updates the progress.',     '<path d="M14.7 6.3a4 4 0 0 0-5.4 5.4L3 18l3 3 6.3-6.3a4 4 0 0 0 5.4-5.4l-2.6 2.6-2.4-.6-.6-2.4 2.6-2.6z"/>'],
        'admin'      => ['Admin',       'Accepts and assigns tickets, and manages people.',         \App\Support\RoleTheme::SHIELD],
    ];
    $shown = array_values(array_filter($roles, fn ($r) => isset($meta[$r])));
@endphp

<fieldset>
    <legend class="mb-1.5 text-sm font-medium text-slate-700">Role</legend>
    <div class="grid gap-2 {{ count($shown) > 2 ? 'sm:grid-cols-3' : 'sm:grid-cols-2' }}">
        @foreach ($shown as $r)
            @php([$label, $hint, $icon] = $meta[$r])
            <label class="relative flex cursor-pointer items-start gap-3 rounded-xl border p-3 transition focus-within:ring-2 focus-within:ring-brand-500 focus-within:ring-offset-1"
                   :class="[{{ $model }} === '{{ $r }}' ? 'border-brand-500 bg-brand-50/70' : 'border-slate-200 bg-white hover:border-slate-300 hover:bg-slate-50', {{ $disabled }} ? 'cursor-not-allowed opacity-60' : '']">
                <input type="radio" name="role" value="{{ $r }}" x-model="{{ $model }}" :disabled="{{ $disabled }}" class="sr-only">
                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg"
                      :class="{{ $model }} === '{{ $r }}' ? 'bg-brand-800 text-white' : 'bg-slate-100 text-slate-500'">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $icon !!}</svg>
                </span>
                <span class="min-w-0">
                    <span class="block text-sm font-semibold text-slate-900">{{ $label }}</span>
                    <span class="mt-0.5 block text-xs leading-snug text-slate-500">{{ $hint }}</span>
                </span>
                <svg x-show="{{ $model }} === '{{ $r }}'" class="absolute right-2.5 top-2.5 h-4 w-4 text-brand-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 13l4 4L19 7"/></svg>
            </label>
        @endforeach
    </div>
    <p x-show="{{ $disabled }}" x-cloak class="mt-2 text-xs text-slate-500">You cannot change your own role. Ask another admin if it needs to change.</p>
    @if ($error) <p role="alert" class="mt-1.5 text-sm text-red-600">{{ $error }}</p> @endif
</fieldset>
