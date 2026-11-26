<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use Illuminate\Http\Request;

/**
 * IT Engineers can LOOK at tickets that are still waiting for an admin to accept them, so they know what is coming.
 * It is strictly read-only: only admins and super admins accept a ticket and assign an engineer, and the ticket itself
 * stays invisible to engineers everywhere else (CompanyScope is not widened), so nothing else can be done to it.
 * Routes are limited to the IT Engineer role.
 */
class AvailableTicketController extends Controller
{
    public function index(Request $request)
    {
        $tickets = Ticket::withoutGlobalScopes()
            ->awaitingAcceptance()
            ->with('user')
            ->orderByRaw("CASE priority WHEN 'critical' THEN 0 WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END")
            ->oldest()
            ->paginate(12)
            ->onEachSide(1);

        return view('tickets.available', compact('tickets'));
    }

    public function show(Request $request, int $id)
    {
        $ticket = Ticket::withoutGlobalScopes()->with(['user', 'attachments'])->find($id);

        if (! $ticket) {
            return redirect()->route('tickets.available')->with('error', 'That ticket could not be found.');
        }

        // Already theirs: use the normal page where they can work on it.
        if ((int) $ticket->assigned_to === (int) $request->user()->id) {
            return redirect()->route('tickets.show', $ticket);
        }

        // An admin accepted it (or it was cancelled) while the engineer was looking at the list.
        if (! ($ticket->status === 'open' && $ticket->assigned_to === null)) {
            return redirect()->route('tickets.available')->with(
                'error',
                "Ticket {$ticket->ticket_no} is no longer waiting for acceptance, so it is not available to view."
            );
        }

        return view('tickets.preview', compact('ticket'));
    }
}
