<x-app-layout>
    <x-slot name="header">Saved replies</x-slot>

    @php
        $field = 'w-full rounded-lg border-slate-300 text-sm focus:border-brand-500 focus:ring-brand-500';
        $card  = 'rounded-2xl border border-slate-200 bg-white shadow-sm';
    @endphp

    <div class="mx-auto max-w-3xl space-y-6">
        <p class="text-sm text-slate-600">
            Save answers you send often, then insert them from a ticket's reply box.
            You can use <code class="rounded bg-slate-100 px-1">{requester}</code>,
            <code class="rounded bg-slate-100 px-1">{ticket_no}</code>,
            <code class="rounded bg-slate-100 px-1">{engineer}</code> and
            <code class="rounded bg-slate-100 px-1">{me}</code>; they are filled in for each ticket.
        </p>

        {{-- New reply --}}
        <form method="POST" action="{{ route('canned-replies.store') }}" class="{{ $card }} space-y-3 p-5">
            @csrf
            <h3 class="font-semibold text-brand-800">New saved reply</h3>
            <input name="title" value="{{ old('title') }}" maxlength="80" placeholder="Title, e.g. Ask for a screenshot" class="{{ $field }}">
            @error('title') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            <textarea name="body" rows="4" maxlength="3000" placeholder="Hi {requester}, ..." class="{{ $field }}">{{ old('body') }}</textarea>
            @error('body') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            <div class="flex items-center justify-between">
                @if ($user->role === 'super_admin')
                    <label class="flex items-center gap-2 text-sm text-slate-600">
                        <input type="checkbox" name="shared" value="1" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500"> Share with all JMS support staff
                    </label>
                @else <span></span> @endif
                <button class="rounded-lg bg-brand-800 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">Save reply</button>
            </div>
        </form>

        @foreach ([['Your replies', $mine], ['Shared replies', $shared]] as [$heading, $list])
            <div class="{{ $card }} p-5">
                <h3 class="mb-3 font-semibold text-brand-800">{{ $heading }}</h3>
                <div class="divide-y divide-slate-100">
                    @forelse ($list as $r)
                        <div class="py-3" x-data="{ edit: false }">
                            <div class="flex items-start justify-between gap-3" x-show="!edit">
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-slate-800">{{ $r->title }}</p>
                                    <p class="mt-1 whitespace-pre-line text-sm text-slate-500">{{ \Illuminate\Support\Str::limit($r->body, 220) }}</p>
                                </div>
                                @if ($r->canBeEditedBy($user))
                                    <div class="flex shrink-0 items-center gap-3 text-sm">
                                        <button type="button" @click="edit = true" class="font-semibold text-brand-600 hover:underline">Edit</button>
                                        <form method="POST" action="{{ route('canned-replies.destroy', $r) }}" onsubmit="return confirm('Delete this saved reply?')">
                                            @csrf @method('DELETE')
                                            <button class="font-semibold text-red-600 hover:underline">Delete</button>
                                        </form>
                                    </div>
                                @endif
                            </div>
                            @if ($r->canBeEditedBy($user))
                                <form method="POST" action="{{ route('canned-replies.update', $r) }}" x-show="edit" x-cloak class="space-y-2">
                                    @csrf @method('PATCH')
                                    <input name="title" value="{{ $r->title }}" maxlength="80" class="{{ $field }}">
                                    <textarea name="body" rows="4" maxlength="3000" class="{{ $field }}">{{ $r->body }}</textarea>
                                    <div class="flex justify-end gap-2">
                                        <button type="button" @click="edit = false" class="rounded-lg px-3 py-1.5 text-sm text-slate-600 ring-1 ring-slate-300 hover:bg-slate-50">Cancel</button>
                                        <button class="rounded-lg bg-brand-800 px-3 py-1.5 text-sm font-semibold text-white hover:bg-brand-700">Save</button>
                                    </div>
                                </form>
                            @endif
                        </div>
                    @empty
                        <p class="py-2 text-sm text-slate-500">Nothing here yet.</p>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>
</x-app-layout>
