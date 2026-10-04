<?php

namespace App\Http\Controllers;

use App\Support\PendingTickets;
use Illuminate\Http\Request;

class PendingTicketController extends Controller
{
    /** Polled every few seconds by admins to keep "Waiting for acceptance" live. */
    public function __invoke(Request $request)
    {
        $after = $request->filled('after') ? max(0, (int) $request->query('after')) : null;

        return response()->json(PendingTickets::feed(PendingTickets::LIMIT, $after))
            ->header('Cache-Control', 'no-store');
    }
}
