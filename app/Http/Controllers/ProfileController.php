<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\Ticket;
use App\Support\Activity;
use App\Support\ActivityKinds;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public const LOGS_PER_PAGE = 10;

    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        $user = $request->user();

        // Activity log: newest first, 10 per page, optional category filter.
        $asked = $request->query('type');
        $type = is_string($asked) && array_key_exists($asked, ActivityKinds::CATEGORIES) ? $asked : null;

        $logs = $user->activityLogs()
            ->when($type, fn ($q, $t) => $q->where('category', $t))
            ->latest('created_at')->latest('id')
            ->paginate(self::LOGS_PER_PAGE, ['*'], 'logs_page')
            ->onEachSide(1)->withQueryString()->fragment('profile-tabs');

        $logCounts = $user->activityLogs()->selectRaw('category, count(*) as total')
            ->groupBy('category')->pluck('total', 'category');

        // Small account summary: what you raised (partners) or what is assigned to you (staff).
        $mine = $user->isStaff() ? Ticket::where('assigned_to', $user->id) : Ticket::where('user_id', $user->id);
        $byStatus = $mine->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        $stats = [
            [$user->isStaff() ? 'Assigned to you' : 'Submitted', $byStatus->sum(), 'text-brand-700'],
            ['Active', ($byStatus['open'] ?? 0) + ($byStatus['in_progress'] ?? 0) + ($byStatus['on_hold'] ?? 0), 'text-amber-600'],
            ['Resolved', ($byStatus['resolved'] ?? 0) + ($byStatus['closed'] ?? 0), 'text-emerald-600'],
        ];

        return view('profile.edit', [
            'user'      => $user,
            'logs'      => $logs,
            'logType'   => $type,
            'logCounts' => $logCounts,
            'stats'     => $stats,
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $changed = collect(['name' => 'name', 'email' => 'email address'])
            ->filter(fn ($label, $field) => $request->user()->isDirty($field))->values();

        $request->user()->save();

        if ($changed->isNotEmpty()) {
            Activity::record($request->user(), 'profile_updated', 'Updated your ' . $changed->join(' and '));
        }

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Switch ticket emails on or off. The bell inside the app always keeps working.
     */
    public function notifications(Request $request): RedirectResponse
    {
        $on = $request->boolean('email_notifications');
        $user = $request->user();

        if ($user->email_notifications !== $on) {
            $user->forceFill(['email_notifications' => $on])->save();
            Activity::record($user, 'profile_updated', $on ? 'Turned ticket emails on' : 'Turned ticket emails off');
        }

        return Redirect::route('profile.edit')->with('status', 'notifications-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
