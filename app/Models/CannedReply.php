<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A saved answer staff can drop into a ticket reply. Placeholders: {requester}, {ticket_no}, {engineer}, {me}.
 * user_id null = JMS-wide (managed by super admins); otherwise private to its owner.
 */
class CannedReply extends Model
{
    protected $fillable = ['user_id', 'title', 'body'];

    public const PLACEHOLDERS = ['{requester}', '{ticket_no}', '{engineer}', '{me}'];

    public function user() { return $this->belongsTo(User::class); }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->where(fn ($q) => $q->whereNull('user_id')->orWhere('user_id', $user->id));
    }

    public function isShared(): bool
    {
        return $this->user_id === null;
    }

    public function canBeEditedBy(User $user): bool
    {
        return $this->isShared() ? $user->role === 'super_admin' : $this->user_id === $user->id;
    }

    /** The text with this ticket's details filled in. */
    public function renderFor(Ticket $ticket, User $me): string
    {
        return strtr($this->body, [
            '{requester}' => $ticket->user?->name ?? 'there',
            '{ticket_no}' => $ticket->ticket_no,
            '{engineer}'  => $ticket->assignee?->name ?? $me->name,
            '{me}'        => $me->name,
        ]);
    }
}
