<?php

namespace App\Support;

use App\Models\Ticket;
use Carbon\CarbonInterface;

/**
 * Builds the "Waiting for acceptance" payload: tickets that are submitted
 * (by a partner user or by staff) but not yet accepted and assigned by an admin.
 *
 * One source of truth for the first page render AND the live JSON feed,
 * so what an admin sees never differs between a page load and a refresh tick.
 */
class PendingTickets
{
    /** Rows shown in the dashboard panel. */
    public const LIMIT = 6;

    /**
     * @param  int|null  $after  Highest ticket id the browser already knows about.
     *                           When given, the payload also reports tickets newer than that.
     */
    public static function feed(int $limit = self::LIMIT, ?int $after = null): array
    {
        $priority = "CASE priority WHEN 'critical' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 ELSE 4 END";

        $items = Ticket::awaitingAcceptance()->with('user')
            ->orderByRaw($priority)->orderBy('created_at')->orderBy('id')
            ->take($limit)->get()
            ->map(fn (Ticket $t) => self::present($t))
            ->values()->all();

        $feed = [
            'count'     => Ticket::awaitingAcceptance()->count(),
            'items'     => $items,
            'latest_id' => (int) Ticket::max('id'),
            'new_count' => 0,
            'new'       => [],
        ];

        if ($after !== null) {
            $new = Ticket::awaitingAcceptance()->with('user')->where('id', '>', $after)->orderBy('id')->get();

            $feed['new_count'] = $new->count();
            $feed['new']       = $new->take(3)->map(fn (Ticket $t) => self::present($t))->values()->all();
        }

        return $feed;
    }

    public static function present(Ticket $t): array
    {
        $user    = $t->user;
        $sla     = $t->slaBadge();
        $minutes = abs((int) $t->created_at->diffInMinutes(now()));

        return [
            'id'               => $t->id,
            'ticket_no'        => $t->ticket_no,
            'subject'          => $t->subject,
            'category'         => $t->category,
            'requester'        => $user?->name ?? 'Unknown user',
            'company'          => $user?->company,
            'initials'         => $user?->initials() ?? '?',
            'avatar'           => $user?->avatarUrl(),
            'priority'         => $t->priority,
            'priority_label'   => $t->priorityLabel(),
            'priority_classes' => $t->priorityClasses(),
            'support_type'     => $t->supportTypeLabel(),
            'scheduled'        => $t->scheduledShort(),
            'sla'              => $sla ? ['text' => $sla[0], 'class' => $sla[1]] : null,
            'submitted'        => $t->created_at->diffForHumans(),
            'waiting'          => $t->created_at->diffForHumans(['syntax' => CarbonInterface::DIFF_ABSOLUTE, 'short' => true]),
            // The longer a ticket sits unaccepted, the louder it gets.
            'waiting_class'    => $minutes >= 120 ? 'text-red-600 font-semibold'
                                : ($minutes >= 30 ? 'text-amber-600 font-medium' : 'text-slate-500'),
            'jms_requested'    => $t->isAskingForJms(),
            'url'              => route('tickets.show', $t),
            'assign_url'       => route('tickets.show', $t) . '#assign',
        ];
    }
}
