{{-- Shared ticket form: submit a ticket (partner dashboard, "New ticket") and edit a ticket (admin, pass $ticket) --}}
@php
    $t = $ticket ?? null;
    $isEdit = $t !== null;
    $when = old('when', $t && $t->scheduled_for ? 'later' : 'now');
    $scheduledValue = old('scheduled_for', $t?->scheduled_for?->format('Y-m-d\TH:i'));
    $icon = fn ($inner) => '<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">' . $inner . '</svg>';

    $categories = [
        'Network / Internet'     => ['Internet down, VPN, Wi-Fi, switches', '<path d="M2 9a15 15 0 0 1 20 0M5 12.5a10 10 0 0 1 14 0M8.5 16a5 5 0 0 1 7 0"/><circle cx="12" cy="19.5" r="1"/>'],
        'Database'                 => ['Crash, slow, cannot connect, data', '<ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v6c0 1.7 3.6 3 8 3s8-1.3 8-3V5M4 11v6c0 1.7 3.6 3 8 3s8-1.3 8-3v-6"/>'],
        'CCTV / Surveillance'      => ['Cameras offline, NVR/DVR, no recording', '<path d="M3 7h13a2 2 0 0 1 2 2v6a2 2 0 0 1-2 2H3z"/><path d="M18 11l3-2v6l-3-2"/>'],
        'Server / Infrastructure'  => ['Server down, storage, backup, power', '<rect x="3" y="3" width="18" height="7" rx="2"/><rect x="3" y="14" width="18" height="7" rx="2"/><path d="M7 6.5h.01M7 17.5h.01"/>'],
        'Hardware'               => ['Computer, laptop, monitor', '<rect x="3" y="4" width="18" height="12" rx="2"/><path d="M8 20h8M12 16v4"/>'],
        'Software'               => ['Apps, installs, errors', '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 9h18M7 6.5h.01M10 6.5h.01"/>'],
        'Email / Account Access' => ['Login, password, email', '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>'],
        'Printer / Peripherals'  => ['Printers, scanners, mouse', '<path d="M7 9V3h10v6M7 17H5a2 2 0 0 1-2-2v-4a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2h-2"/><rect x="7" y="14" width="10" height="7"/>'],
        'Security'               => ['Suspicious email, virus', '<path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6l8-3z"/><path d="M9 12l2 2 4-4"/>'],
        'Other'                  => ['Anything else', '<circle cx="12" cy="12" r="9"/><path d="M12 8v4M12 16h.01"/>'],
    ];

    // Shown in the description box once a category is picked, so partners include what IT needs first time.
    $hints = [
        'Network / Internet'      => 'Which site or office is affected? Is the whole internet down or only some things? Since when? Have you tried restarting the router or switch?',
        'Database'                => 'Which database or system is affected? What error do you see? When did it stop working? Was anything changed or updated recently?',
        'CCTV / Surveillance'     => 'Which cameras, recorder (NVR/DVR) or location? Offline, blurry or not recording? Since when? Do you need footage from a certain date or time?',
        'Server / Infrastructure' => 'Which server or storage device? What stopped working and who is affected? Any lights, alarms or error messages? Since when?',
        'Hardware'                => 'Which device (computer, laptop, monitor)? What happens when you turn it on? Any noise, smell or error message?',
        'Software'                => 'Which program? What exactly happens and what is the error message? When did it start?',
        'Email / Account Access'  => 'Which account or email address? What happens when you try to sign in? Is anyone else affected?',
        'Printer / Peripherals'   => 'Which printer or device? What happens when you print? Any error on the screen?',
        'Security'                => 'What did you notice (strange email, virus warning, unknown login)? Did you click anything or enter a password?',
        'Other'                   => 'Tell us what you need help with, what you already tried and when it started.',
    ];

    // What to attach, per category (shown under the file picker).
    $attachHints = [
        'Network / Internet'      => 'A screenshot of the error, or a photo of the router or switch lights.',
        'Database'                => 'A screenshot of the error message, or the database log file.',
        'CCTV / Surveillance'     => 'A photo of the recorder (NVR/DVR) screen or error, or a screenshot of the camera view.',
        'Server / Infrastructure' => 'A photo of the server lights or screen, or the error or event log.',
        'Hardware'                => 'A photo of the device and any message on its screen.',
        'Software'                => 'A screenshot of the error message.',
        'Email / Account Access'  => 'A screenshot of the sign-in error.',
        'Printer / Peripherals'   => 'A photo of the printer display, or a screenshot of the error.',
        'Security'                => 'A screenshot of the warning or the suspicious email.',
    ];

    $priorities = [
        'low'      => ['Low', 'Minor issue, no rush', 'bg-slate-400'],
        'medium'   => ['Medium', 'Slows my work down', 'bg-sky-500'],
        'high'     => ['High', 'I cannot work properly', 'bg-orange-500'],
        'critical' => ['Critical', 'A key system or many people are down', 'bg-red-500'],
    ];

    $ok  = 'border-slate-300 focus:border-brand-500 focus:ring-brand-500';
    $bad = 'border-red-400 focus:border-red-500 focus:ring-red-500';
@endphp

<form method="POST" action="{{ $isEdit ? route('tickets.details', $t) : route('tickets.store') }}" enctype="multipart/form-data" novalidate
      x-data="{ loading: false, when: '{{ $when }}', count: {{ strlen(old('description', $t?->description ?? '')) }}, cat: @js(old('category', $t?->category ?? '')), hints: @js($hints), attachHints: @js($attachHints) }" @submit="loading = true" @pageshow.window="loading = false"
      class="space-y-8">
    @csrf
    @if ($isEdit) @method('PUT') @endif

    @if ($errors->any())
        <div role="alert" class="flex gap-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            <svg class="mt-0.5 h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="M12 8v4M12 16h.01"/></svg>
            <span>Please fix the {{ $errors->count() === 1 ? 'highlighted field' : $errors->count() . ' highlighted fields' }} below and submit again.</span>
        </div>
    @endif

    {{-- Step 1: category --}}
    <section>
        <h3 class="mb-3 flex items-center gap-2.5 text-sm font-semibold text-slate-800">
            <span class="flex h-6 w-6 items-center justify-center rounded-full bg-brand-800 text-xs font-bold text-white">1</span>
            What kind of problem is it?
        </h3>
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4">
            @foreach ($categories as $name => [$hint, $paths])
                <label class="cursor-pointer">
                    <input type="radio" name="category" value="{{ $name }}" x-model="cat" class="peer sr-only" @checked(old('category', $t?->category) === $name)>
                    <span class="flex h-full flex-col items-start gap-1.5 rounded-xl border bg-white p-3.5 shadow-sm transition hover:border-brand-400 hover:shadow peer-checked:border-brand-600 peer-checked:bg-brand-50 peer-checked:ring-2 peer-checked:ring-brand-600/30 peer-focus-visible:ring-2 peer-focus-visible:ring-brand-500 {{ $errors->has('category') ? 'border-red-300' : 'border-slate-200' }}">
                        <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-brand-50 text-brand-600">{!! $icon($paths) !!}</span>
                        <span class="text-sm font-semibold text-slate-800">{{ $name }}</span>
                        <span class="text-xs leading-snug text-slate-500">{{ $hint }}</span>
                    </span>
                </label>
            @endforeach
        </div>
        @error('category') <p role="alert" class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
    </section>

    {{-- Step 2: priority --}}
    <section>
        <h3 class="mb-3 flex items-center gap-2.5 text-sm font-semibold text-slate-800">
            <span class="flex h-6 w-6 items-center justify-center rounded-full bg-brand-800 text-xs font-bold text-white">2</span>
            How urgent is it?
        </h3>
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($priorities as $key => [$label, $hint, $dot])
                <label class="cursor-pointer">
                    <input type="radio" name="priority" value="{{ $key }}" class="peer sr-only" @checked(old('priority', $t?->priority ?? 'medium') === $key)>
                    <span class="flex h-full items-start gap-3 rounded-xl border border-slate-200 bg-white p-3.5 shadow-sm transition hover:border-brand-400 hover:shadow peer-checked:border-brand-600 peer-checked:bg-brand-50 peer-checked:ring-2 peer-checked:ring-brand-600/30 peer-focus-visible:ring-2 peer-focus-visible:ring-brand-500">
                        <span class="mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full {{ $dot }}"></span>
                        <span>
                            <span class="block text-sm font-semibold text-slate-800">{{ $label }}</span>
                            <span class="block text-xs leading-snug text-slate-500">{{ $hint }}</span>
                        </span>
                    </span>
                </label>
            @endforeach
        </div>
        @error('priority') <p role="alert" class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
    </section>

    {{-- Step 3: details --}}
    <section class="space-y-5">
        <h3 class="flex items-center gap-2.5 text-sm font-semibold text-slate-800">
            <span class="flex h-6 w-6 items-center justify-center rounded-full bg-brand-800 text-xs font-bold text-white">3</span>
            Tell us what happened
        </h3>

        <div>
            <label for="subject" class="mb-1.5 block text-sm font-medium text-slate-700">Subject</label>
            <input id="subject" name="subject" value="{{ old('subject', $t?->subject) }}" maxlength="150" placeholder="e.g. Cannot connect to the office Wi-Fi"
                   aria-invalid="{{ $errors->has('subject') ? 'true' : 'false' }}"
                   class="w-full rounded-lg py-2.5 text-sm shadow-sm placeholder:text-slate-400 {{ $errors->has('subject') ? $bad : $ok }}">
            @error('subject') <p role="alert" class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="description" class="mb-1.5 block text-sm font-medium text-slate-700">Description</label>
            <textarea id="description" name="description" rows="6" maxlength="5000" @input="count = $event.target.value.length"
                      placeholder="What happened? Any error message? When did it start? What have you already tried?" :placeholder="hints[cat] || 'What happened? Any error message? When did it start? What have you already tried?'"
                      aria-invalid="{{ $errors->has('description') ? 'true' : 'false' }}"
                      class="w-full rounded-lg text-sm shadow-sm placeholder:text-slate-400 {{ $errors->has('description') ? $bad : $ok }}">{{ old('description', $t?->description) }}</textarea>
            <div class="mt-1.5 flex items-start justify-between gap-3">
                <div>@error('description') <p role="alert" class="text-sm text-red-600">{{ $message }}</p> @enderror</div>
                <p class="shrink-0 text-xs text-slate-400"><span x-text="count">0</span> / 5000</p>
            </div>
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label for="contact_phone" class="mb-1.5 block text-sm font-medium text-slate-700">Contact number <span class="font-normal text-slate-400">(optional)</span></label>
                <input id="contact_phone" name="contact_phone" type="tel" value="{{ old('contact_phone', $t?->contact_phone) }}" autocomplete="tel" placeholder="+63 900 000 0000"
                       aria-invalid="{{ $errors->has('contact_phone') ? 'true' : 'false' }}"
                       class="w-full rounded-lg py-2.5 text-sm shadow-sm placeholder:text-slate-400 {{ $errors->has('contact_phone') ? $bad : $ok }}">
                @error('contact_phone') <p role="alert" class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="location" class="mb-1.5 block text-sm font-medium text-slate-700">Branch / address <span class="font-normal text-slate-400">(optional)</span></label>
                <input id="location" name="location" value="{{ old('location', $t?->location) }}" maxlength="255" placeholder="Full address or a Google Maps link"
                       class="w-full rounded-lg py-2.5 text-sm shadow-sm placeholder:text-slate-400 {{ $errors->has('location') ? $bad : $ok }}">
                <p class="mt-1.5 text-xs text-slate-400">If our team needs to visit you, they use this to find you on Google Maps. IT will decide whether your issue is handled remotely or on-site.</p>
                @error('location') <p role="alert" class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        {{-- Screenshots, error photos and log files. Added after submitting, on the ticket page. --}}
        @unless ($isEdit)
            <x-attachment-picker>
                <span x-text="attachHints[cat] || 'Screenshots, photos of the error and log files help us fix it faster.'"></span>
            </x-attachment-picker>
            @if ($errors->any() && ! $errors->has('files') && ! $errors->has('files.*'))
                <p class="-mt-3 text-xs text-amber-700">If you had chosen files, please choose them again: the browser cannot keep them when a form needs a correction.</p>
            @endif
        @endunless
    </section>

    {{-- Step 4: when --}}
    <section class="space-y-3">
        <h3 class="flex items-center gap-2.5 text-sm font-semibold text-slate-800">
            <span class="flex h-6 w-6 items-center justify-center rounded-full bg-brand-800 text-xs font-bold text-white">4</span>
            When do you need help?
        </h3>
        <div class="grid gap-3 sm:grid-cols-2">
            @foreach ([
                ['now', 'As soon as possible', 'We start as soon as an engineer is available.', '<path d="M13 2 4 14h7l-1 8 9-12h-7l1-8z"/>'],
                ['later', 'Schedule for later', 'Pick a date and time that suits you.', '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>'],
            ] as [$val, $label, $hint, $paths])
                <label class="cursor-pointer">
                    <input type="radio" name="when" value="{{ $val }}" x-model="when" class="peer sr-only">
                    <span class="flex h-full items-start gap-3 rounded-xl border border-slate-200 bg-white p-3.5 shadow-sm transition hover:border-brand-400 hover:shadow peer-checked:border-brand-600 peer-checked:bg-brand-50 peer-checked:ring-2 peer-checked:ring-brand-600/30 peer-focus-visible:ring-2 peer-focus-visible:ring-brand-500">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-brand-600">{!! $icon($paths) !!}</span>
                        <span>
                            <span class="block text-sm font-semibold text-slate-800">{{ $label }}</span>
                            <span class="block text-xs leading-snug text-slate-500">{{ $hint }}</span>
                        </span>
                    </span>
                </label>
            @endforeach
        </div>
        @error('when') <p role="alert" class="text-sm text-red-600">{{ $message }}</p> @enderror

        <div x-show="when === 'later'" x-cloak class="max-w-md">
            <label class="mb-1.5 block text-sm font-medium text-slate-700">Preferred date and time</label>
            <x-datetime-picker name="scheduled_for" :value="$scheduledValue" :now="now()->format('Y-m-d H:i')" :lead="$isEdit ? 5 : 30" :invalid="$errors->has('scheduled_for')" />
            <p class="mt-1.5 text-xs text-slate-400">Our team will confirm the visit or call for this time. You can schedule up to 90 days ahead.</p>
            @error('scheduled_for') <p role="alert" class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
    </section>

    <div class="flex flex-col gap-3 border-t border-slate-100 pt-6 sm:flex-row sm:items-center sm:justify-between">
        @if ($isEdit)
            <a href="{{ route('tickets.show', $t) }}" class="text-sm font-medium text-slate-600 hover:underline">Cancel</a>
        @else
            <p class="text-xs text-slate-500">You will get a ticket number and can follow every update and reply right here.</p>
        @endif
        <button type="submit" :disabled="loading"
                class="flex items-center justify-center gap-2 rounded-lg bg-brand-800 px-6 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-70">
            <svg x-show="loading" x-cloak class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg>
            <span x-text="loading ? '{{ $isEdit ? 'Saving...' : 'Submitting...' }}' : '{{ $isEdit ? 'Save changes' : 'Submit ticket' }}'">{{ $isEdit ? 'Save changes' : 'Submit ticket' }}</span>
        </button>
    </div>
</form>
