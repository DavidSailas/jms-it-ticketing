<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $filter = $request->query('filter') === 'unread' ? 'unread' : 'all';

        $query = $filter === 'unread' ? $user->unreadNotifications() : $user->notifications();
        $items = $query->latest()->paginate(15)->onEachSide(1)->withQueryString()
            ->through(fn ($n) => $user->presentNotification($n));

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

    /** Marks one notification read, then goes to the ticket it is about. */
    public function open(Request $request, string $id)
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        $url = $notification->data['url'] ?? route('dashboard', absolute: false);

        return redirect(str_starts_with($url, '/') ? $url : route('dashboard'));
    }
}
