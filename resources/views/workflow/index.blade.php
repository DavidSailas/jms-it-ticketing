<x-app-layout>
    <x-slot name="header">Workflow Guide</x-slot>

    @php($tick = '<svg class="mx-auto h-4 w-4 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-label="Yes"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12 5 5L20 7"/></svg>')
    @php($dash = '<span class="text-slate-300" aria-label="No">&ndash;</span>')
    @php($status = fn ($s) => new \App\Models\Ticket(['status' => $s]))

    {{-- Title --}}
    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h2 class="text-xl font-extrabold tracking-tight text-brand-800">How tickets move through JMS One IT</h2>
            <p class="mt-1 max-w-2xl text-sm text-slate-500">Every problem becomes a ticket, and every ticket follows the same path from reported to fixed and rated. Click any step or status to learn more.</p>
        </div>
        <a href="{{ route('workflow.pdf') }}" class="inline-flex shrink-0 items-center justify-center gap-2 rounded-lg bg-brand-800 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-brand-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v11m0 0-4-4m4 4 4-4M5 20h14"/></svg>
            Download PDF
        </a>
    </div>

    {{-- 1. The five steps --}}
    <section class="mb-8 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" x-data="{ step: 1 }" aria-labelledby="steps-heading">
        <h3 id="steps-heading" class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-500">The big picture in 5 steps</h3>

        <div class="grid grid-cols-2 gap-2 sm:grid-cols-5">
            @foreach ([[1, 'Report', 'Customer logs a ticket', 'sky'], [2, 'Accept & assign', 'Admin picks an engineer', 'violet'], [3, 'Fix', 'Engineer works on it', 'amber'], [4, 'Resolve', 'Engineer writes the fix', 'emerald'], [5, 'Confirm', 'Customer rates it 1 to 5', 'slate']] as [$n, $title, $sub, $c])
                <button type="button" @click="step = {{ $n }}" :aria-pressed="step === {{ $n }}"
                        class="rounded-xl border p-3 text-left transition focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500"
                        :class="step === {{ $n }} ? 'border-brand-600 bg-brand-50 ring-2 ring-brand-100' : 'border-slate-200 bg-white hover:border-slate-300'">
                    <span class="flex h-7 w-7 items-center justify-center rounded-full text-sm font-bold text-white
                        {{ ['sky' => 'bg-sky-500', 'violet' => 'bg-violet-600', 'amber' => 'bg-amber-500', 'emerald' => 'bg-emerald-600', 'slate' => 'bg-slate-700'][$c] }}">{{ $n }}</span>
                    <span class="mt-2 block text-sm font-semibold text-slate-800">{{ $title }}</span>
                    <span class="block text-xs text-slate-500">{{ $sub }}</span>
                </button>
            @endforeach
        </div>

        <div class="mt-4 rounded-xl bg-slate-50 p-4 text-sm leading-relaxed text-slate-700" aria-live="polite">
            <div x-show="step === 1">
                <p class="font-semibold text-slate-900">1. Report</p>
                <p class="mt-1"><span class="font-medium">Who:</span> a customer, meaning a person from one of our partner companies.</p>
                <p><span class="font-medium">What happens:</span> they describe the problem, pick a category and priority, choose <em>as soon as possible</em> or book a date and time, and can attach up to {{ \App\Support\Attachments::MAX_FILES }} images, screenshots or log files. The ticket gets a number like <code class="rounded bg-white px-1 text-xs">JMS-261010-AB12</code> and its status is <strong>Open</strong>.</p>
                <p class="mt-1 text-slate-500">Until an admin accepts it, they can still cancel it or move the booking to a new time.</p>
            </div>
            <div x-show="step === 2" x-cloak>
                <p class="font-semibold text-slate-900">2. Accept &amp; assign</p>
                <p class="mt-1"><span class="font-medium">Who:</span> an Admin or Super Admin of JMS One IT.</p>
                <p><span class="font-medium">What happens:</span> the admin opens the ticket (or uses <em>Accept &amp; assign</em> from the list), picks an engineer, chooses remote or on-site support, sets the priority and can leave a note. Status becomes <strong>Assigned</strong>.</p>
                <p><span class="font-medium">Double-booking check:</span> engineers who already have a visit or call within {{ \App\Models\Ticket::BOOKING_WINDOW_MINUTES }} minutes of the booked time are marked <em>Booked</em>. The admin can pick someone else, or tick <em>Assign anyway</em>.</p>
                <p class="mt-1 text-slate-500">Only Admins and Super Admins assign. IT Engineers cannot pick tickets themselves.</p>
            </div>
            <div x-show="step === 3" x-cloak>
                <p class="font-semibold text-slate-900">3. Fix</p>
                <p class="mt-1"><span class="font-medium">Who:</span> the assigned IT engineer.</p>
                <p><span class="font-medium">What happens:</span> the engineer sets the ticket <strong>In progress</strong>, replies to the requester and can add internal notes the requester never sees. If they must wait for a part or a person, they set it <strong>On hold</strong>.</p>
                <p class="mt-1 text-slate-500">Cannot solve it? Tell the admin on the ticket; they can reassign it to another engineer.</p>
            </div>
            <div x-show="step === 4" x-cloak>
                <p class="font-semibold text-slate-900">4. Resolve</p>
                <p class="mt-1"><span class="font-medium">Who:</span> the engineer (or an admin).</p>
                <p><span class="font-medium">What happens:</span> they mark it <strong>Resolved</strong> and write what was done (at least 10 characters). The requester is notified.</p>
            </div>
            <div x-show="step === 5" x-cloak>
                <p class="font-semibold text-slate-900">5. Confirm</p>
                <p class="mt-1"><span class="font-medium">Who:</span> the person who reported it.</p>
                <p><span class="font-medium">What happens:</span> they confirm it is fixed and rate it 1 to 5. The ticket becomes <strong>Closed</strong>. If it is not fixed, they can <strong>reopen</strong> it and it goes back to the engineer.</p>
            </div>
        </div>
    </section>

    {{-- 2. Status journey --}}
    <section class="mb-8 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" x-data="{ s: 'open' }" aria-labelledby="status-heading">
        <h3 id="status-heading" class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-500">The life of a ticket</h3>

        <div class="flex flex-wrap items-center gap-x-2 gap-y-3">
            @foreach (['open', 'assigned', 'in_progress', 'resolved', 'closed'] as $i => $k)
                @if ($i > 0)
                    <svg class="h-4 w-4 shrink-0 text-slate-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m9 6 6 6-6 6"/></svg>
                @endif
                <button type="button" @click="s = '{{ $k }}'" class="rounded-full focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500" :class="s === '{{ $k }}' ? 'ring-2 ring-brand-300 ring-offset-2' : ''"><x-ticket-pill :ticket="$status($k)" /></button>
            @endforeach
        </div>
        <div class="mt-3 flex flex-wrap items-center gap-x-2 gap-y-3 text-xs text-slate-500">
            <span>Side paths:</span>
            <button type="button" @click="s = 'on_hold'" class="rounded-full focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500" :class="s === 'on_hold' ? 'ring-2 ring-brand-300 ring-offset-2' : ''"><x-ticket-pill :ticket="$status('on_hold')" /></button>
            <span>pause and continue</span>
            <button type="button" @click="s = 'cancelled'" class="ml-2 rounded-full focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500" :class="s === 'cancelled' ? 'ring-2 ring-brand-300 ring-offset-2' : ''"><x-ticket-pill :ticket="$status('cancelled')" /></button>
            <span>by the customer before assignment, or by an admin at any time</span>
        </div>

        <div class="mt-4 rounded-xl bg-slate-50 p-4 text-sm text-slate-700" aria-live="polite">
            <p x-show="s === 'open'"><strong>Open.</strong> Submitted and waiting for an admin to accept it. The requester can still cancel it or move the booking.</p>
            <p x-show="s === 'assigned'" x-cloak><strong>Assigned.</strong> An admin accepted it and handed it to an IT engineer. Moved by: admin or super admin. From here the requester can only ask to reschedule, and the team approves or declines.</p>
            <p x-show="s === 'in_progress'" x-cloak><strong>In progress.</strong> The engineer is working on it. Moved by: the engineer.</p>
            <p x-show="s === 'on_hold'" x-cloak><strong>On hold.</strong> Paused, for example while waiting for a part or for the user. Moved by: the engineer or an admin.</p>
            <p x-show="s === 'resolved'" x-cloak><strong>Resolved.</strong> Fixed, with a written note of what was done. The requester now confirms or reopens it.</p>
            <p x-show="s === 'closed'" x-cloak><strong>Closed.</strong> The requester confirmed the fix and gave a rating from 1 to 5.</p>
            <p x-show="s === 'cancelled'" x-cloak><strong>Cancelled.</strong> Withdrawn by the requester while the ticket is Open and not yet assigned, or cancelled by an admin or super admin at any stage, with a reason.</p>
        </div>
    </section>

    {{-- Booking, rescheduling and cancelling --}}
    <section class="mb-8 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" aria-labelledby="booking-heading">
        <h3 id="booking-heading" class="mb-1 text-sm font-semibold uppercase tracking-wide text-slate-500">Booking, rescheduling and cancelling</h3>
        <p class="mb-4 text-sm text-slate-500">Customers choose when they need help. What they can change depends on how far the ticket has moved.</p>
        <div class="grid gap-3 lg:grid-cols-3">
            <div class="rounded-xl border border-sky-200 bg-sky-50 p-4">
                <p class="text-sm font-semibold text-sky-900">1. Not accepted yet (Open)</p>
                <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-sky-900">
                    <li>The customer can <strong>cancel</strong> the ticket.</li>
                    <li>The customer can <strong>reschedule</strong>, and the new time applies straight away.</li>
                    <li>Admins are notified of the change.</li>
                </ul>
            </div>
            <div class="rounded-xl border border-violet-200 bg-violet-50 p-4">
                <p class="text-sm font-semibold text-violet-900">2. Engineer assigned (Assigned)</p>
                <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-violet-900">
                    <li>The customer can <strong>request</strong> a new time and add a reason.</li>
                    <li>The customer can <strong>no longer cancel online</strong>. An admin or super admin cancels it, with a reason the customer sees.</li>
                    <li>The booking stays as it is until an <strong>admin, super admin or the assigned engineer</strong> approves it.</li>
                    <li>The customer is told the result and a reply is added to the ticket.</li>
                    <li>Approving a time that clashes with another booking needs <em>Approve anyway</em>.</li>
                </ul>
            </div>
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                <p class="text-sm font-semibold text-amber-900">3. Locked</p>
                <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-amber-900">
                    <li>Once the engineer is <strong>working on it</strong>, or the ticket is on hold, the customer replies on the ticket to agree a new time.</li>
                    <li>Within <strong>{{ \App\Models\Ticket::RESCHEDULE_CUTOFF_HOURS }} hours</strong> of the booked time it can no longer be moved.</li>
                    <li>A new time can be at most 90 days ahead.</li>
                </ul>
            </div>
        </div>
    </section>

    {{-- 3. Who does what, step by step --}}
    <section class="mb-8 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" aria-labelledby="paths-heading">
        <h3 id="paths-heading" class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-500">Who does what</h3>
        <ol class="space-y-2">
            @foreach ([['Customer', 'Logs the ticket: problem, category, priority, preferred time and optional images or files.'], ['Admin or Super Admin', 'Accepts it and assigns one of our IT Engineers, avoiding double bookings.'], ['IT Engineer', 'Works on it: in progress, on hold if needed, then resolved with a note.'], ['Customer', 'Confirms it is fixed and rates it 1 to 5, or reopens it if the fix did not work. Before that, can reschedule or cancel as shown above.']] as $i => [$who, $what])
                <li class="flex gap-3 rounded-xl bg-slate-50 p-3">
                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-sky-500 text-xs font-bold text-white">{{ $i + 1 }}</span>
                    <span class="text-sm"><span class="block text-[11px] font-bold uppercase tracking-wide text-sky-700">{{ $who }}</span><span class="text-slate-700">{{ $what }}</span></span>
                </li>
            @endforeach
        </ol>
    </section>

    {{-- 4. Who can do what --}}
    <section class="mb-8 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" aria-labelledby="matrix-heading">
        <div class="px-5 pt-5"><h3 id="matrix-heading" class="text-sm font-semibold uppercase tracking-wide text-slate-500">Who can do what</h3></div>
        <div class="mt-3 overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th scope="col" class="px-4 py-3 text-left">Action</th>
                        @foreach (['Customer', 'IT Engineer', 'Admin', 'Super Admin'] as $h)
                            <th scope="col" class="whitespace-nowrap px-3 py-3 text-center">{{ $h }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ([
                        ['Log a ticket',                         [1, 0, 0, 0]],
                        ['Attach images and files',              [1, 1, 1, 1]],
                        ['Cancel it before it is accepted',      [1, 0, 0, 0]],
                        ['Reschedule or request a new time',     [1, 0, 0, 0]],
                        ['Cancel after an engineer is assigned', [0, 0, 1, 1]],
                        ['Accept and assign a ticket',           [0, 0, 1, 1]],
                        ['Approve or decline a reschedule',      [0, 1, 1, 1]],
                        ['Update status and resolve',            [0, 1, 1, 1]],
                        ['Confirm, rate or reopen',              [1, 0, 0, 0]],
                        ['Manage customers and engineers',       [0, 0, 1, 1]],
                        ['Manage admins, companies and reports', [0, 0, 0, 1]],
                        ['Create and edit roles',                [0, 0, 0, 1]],
                    ] as [$action, $cells])
                        <tr class="hover:bg-slate-50/60">
                            <th scope="row" class="whitespace-nowrap px-4 py-2.5 text-left font-medium text-slate-700">{{ $action }}</th>
                            @foreach ($cells as $on)
                                <td class="px-3 py-2.5 text-center">{!! $on ? $tick : $dash !!}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="px-5 py-3 text-xs text-slate-500">Customers only see their own tickets. Engineers only see the tickets assigned to them. "Confirm, rate or reopen" is for the customer who logged the ticket. Engineers approve or decline reschedule requests only on their own tickets. Super Admins can change what the Admin and IT Engineer roles may do under Roles, so this table shows the standard setup.</p>
    </section>

    {{-- 5. Targets and rules --}}
    <section class="mb-8" aria-labelledby="rules-heading">
        <h3 id="rules-heading" class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500">Fix-by times</h3>
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            @foreach (['critical' => ['Critical', 'bg-red-600'], 'high' => ['High', 'bg-orange-500'], 'medium' => ['Medium', 'bg-sky-500'], 'low' => ['Low', 'bg-slate-500']] as $k => [$label, $bg])
                <div class="rounded-xl {{ $bg }} px-4 py-3 text-center text-white shadow-sm">
                    <p class="text-sm font-semibold">{{ $label }}</p>
                    <p class="text-xs opacity-90">within {{ \App\Models\Ticket::SLA_HOURS[$k] }} hours</p>
                </div>
            @endforeach
        </div>

        <h3 class="mb-3 mt-6 text-sm font-semibold uppercase tracking-wide text-slate-500">House rules</h3>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ([
                ['Visibility', 'Customers see only their own tickets. IT Engineers see only the tickets assigned to them. Admins and Super Admins see every ticket.'],
                ['Assignment', 'Only Admins and Super Admins assign tickets. Engineers cannot pick tickets themselves.'],
                ['Internal notes', 'Engineers and admins can leave notes the requester never sees.'],
                ['Remote or on-site', 'The admin chooses when assigning. On-site visits appear on the Schedule calendar.'],
                ['Rescheduling', 'Before acceptance the new time applies at once. After assignment it is a request that the team approves. A booking that starts within ' . \App\Models\Ticket::RESCHEDULE_CUTOFF_HOURS . ' hours can no longer be moved.'],
                ['Double-booking', 'The assign screen marks engineers who already have a visit or call within ' . \App\Models\Ticket::BOOKING_WINDOW_MINUTES . ' minutes. Assigning them anyway needs a tick.'],
                ['Images and files', 'Customers can attach up to ' . \App\Support\Attachments::MAX_FILES . ' images or files (' . \App\Support\Attachments::label() . ', ' . \App\Support\Attachments::MAX_MB . ' MB each) when logging a ticket and in every reply.'],
                ['Cancelling', 'Customers cancel only before an engineer is assigned. After that, an admin or super admin cancels with a reason, and the customer and engineer are told.'],
                ['Roles and permissions', 'Super Admins create roles under Roles, tick what each role may do, and add people to it. Admin-level roles see every ticket; engineer-level roles see only their own.'],
                ['Reopening', 'If the fix did not work, the requester reopens a resolved ticket and it goes back to the engineer.'],
                ['Notifications', 'People are told when a ticket is assigned, updated or replied to. Admins also see a live waiting-for-acceptance list.'],
            ] as [$t, $d])
                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-sm font-semibold text-brand-800">{{ $t }}</p>
                    <p class="mt-1 text-sm text-slate-600">{{ $d }}</p>
                </div>
            @endforeach
        </div>
    </section>
</x-app-layout>
