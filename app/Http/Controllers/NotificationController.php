<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $filter = $request->query('filter') === 'unread' ? 'unread' : 'all';

        $query = $filter === 'unread' ? $user->unreadNotifications() : $user->notifications();
        $page  = $query->latest()->paginate(15)->onEachSide(1)->withQueryString();
        $gone  = $user->unavailableTicketIds($page->items());
        $items = $page->through(fn ($n) => $user->presentNotification($n, $gone));

        return view('notifications.index', [
            'items'  => $items,
            'filter' => $filter,
            'unread' => $user->unreadNotifications()->count(),
        ]);
    }

    /** Polled by the bell every few seconds to show new activity live. */
    public function feed(Request $request)
    {
        return response()->json($request->user()->notificationFeed())
            ->header('Cache-Control', 'no-store');
    }

    public function readAll(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();

        return $request->expectsJson()
            ? response()->json(['ok' => true])
            : back()->with('success', 'All notifications marked as read.');
    }

    /**
     * Marks one notification read, then goes to the ticket it is about.
     *
     * A notification can outlive the person's access to its ticket: an IT Engineer only sees tickets assigned
     * to them, so once it is reassigned, finished or removed the link would be a bare 404. In that case we
     * explain it and land on their ticket list instead.
     */
    public function open(Request $request, string $id)
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        $url = $notification->data['url'] ?? route('dashboard', absolute: false);
        $url = str_starts_with($url, '/') ? $url : route('dashboard', absolute: false);

        $ticketId = $notification->data['ticket_id'] ?? (preg_match('#^/tickets/(\d+)#', $url, $m) ? (int) $m[1] : null);

        // Ticket is already limited to what this person may see (see CompanyScope), so "missing" also means "not yours any more".
        if ($ticketId && ! Ticket::query()->whereKey($ticketId)->exists()) {
            // An engineer can still look at a ticket that went back to waiting for acceptance (read only).
            if ($request->user()->role === 'it_support' && Ticket::withoutGlobalScopes()->awaitingAcceptance()->whereKey($ticketId)->exists()) {
                return redirect()->route('tickets.preview', $ticketId);
            }

            $no = $notification->data['ticket_no'] ?? null;

            return redirect()->route('tickets.index')->with(
                'error',
                ($no ? "Ticket {$no} is" : 'That ticket is') . ' no longer available to you. It may have been reassigned, closed or removed.'
            );
        }

        return redirect($url);
    }
}
