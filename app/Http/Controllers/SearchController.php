<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Ticket;
use App\Models\User;
use App\Support\DashboardCharts;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Global search (Ctrl+K). Everything here follows the same visibility rules as the screens it links to:
 * partners find only their own tickets, engineers only what is assigned to them, admins stay inside their company,
 * and only admins find people and only super admins find companies. Nobody can find what they could not open.
 */
class SearchController extends Controller
{
    private const MIN = 2;
    private const TICKETS = 6;
    private const PEOPLE = 5;
    private const COMPANIES = 4;

    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $q = $this->clean((string) $request->query('q'));

        $out = ['q' => $q, 'tickets' => [], 'people' => [], 'companies' => []];

        if (mb_strlen($q) >= self::MIN) {
            $out['tickets'] = $this->tickets($user, $q);

            if (in_array($user->role, ['admin', 'super_admin'], true)) {
                $out['people'] = $this->people($user, $q);
            }
            if ($user->role === 'super_admin') {
                $out['companies'] = $this->companies($q);
            }
        }

        return response()->json($out)->header('Cache-Control', 'no-store');
    }

    /** Trim, collapse spaces, and drop the characters that mean something special inside a LIKE pattern. */
    private function clean(string $q): string
    {
        return mb_substr(trim(preg_replace('/\s+/', ' ', str_replace(['%', '_', '\\'], '', $q))), 0, 60);
    }

    private function tickets(User $user, string $q): array
    {
        $base = Ticket::query();

        if ($user->role === 'user') {
            $base->where('user_id', $user->id);
        } elseif ($user->role === 'it_support') {
            $base->where('assigned_to', $user->id);
        }

        return $base->with('user:id,name,company')
            ->where(fn ($w) => $w->where('subject', 'like', "%{$q}%")
                ->orWhere('ticket_no', 'like', "%{$q}%")
                ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$q}%")->orWhere('company', 'like', "%{$q}%")))
            ->orderByRaw('CASE WHEN ticket_no LIKE ? THEN 0 ELSE 1 END', ["{$q}%"])
            ->orderByDesc('id')
            ->limit(self::TICKETS)->get()
            ->map(fn (Ticket $t) => [
                'id'           => $t->id,
                'no'           => $t->ticket_no,
                'title'        => $t->subject,
                'sub'          => collect([$t->user?->name, $t->user?->company])->filter()->implode(' - '),
                'status'       => $t->status,
                'status_label' => $t->statusLabel(),
                'color'        => DashboardCharts::STATUS_COLORS[$t->status] ?? '#94a3b8',
                'priority'     => $t->priority,
                'url'          => route('tickets.show', $t, absolute: false),
            ])->all();
    }

    private function people(User $user, string $q): array
    {
        return User::query()->whereIn('role', $user->manageableRoles())->inMyCompany()
            ->where(fn ($w) => $w->where('name', 'like', "%{$q}%")
                ->orWhere('username', 'like', "%{$q}%")
                ->orWhere('email', 'like', "%{$q}%")
                ->orWhere('users.company', 'like', "%{$q}%"))
            ->orderBy('name')->limit(self::PEOPLE)->get()
            ->map(fn (User $u) => [
                'id'    => $u->id,
                'title' => $u->name,
                'sub'   => collect([$u->roleLabel(), $u->company, $u->email])->filter()->implode(' - '),
                'url'   => route('users.show', $u, absolute: false),
            ])->all();
    }

    private function companies(string $q): array
    {
        return Company::query()->where('name', 'like', "%{$q}%")->orderBy('name')->limit(self::COMPANIES)->get()
            ->map(fn (Company $c) => [
                'id'    => $c->id,
                'title' => $c->name,
                'sub'   => 'Partner company',
                'url'   => route('companies.show', $c, absolute: false),
            ])->all();
    }
}
