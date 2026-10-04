<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Ticket extends Model
{
    public const CATEGORIES = ['Hardware', 'Software', 'Network / Internet', 'Email / Account Access', 'Printer / Peripherals', 'Security', 'Other'];
    public const PRIORITIES = ['low', 'medium', 'high', 'critical'];

    /**
     * open        = submitted, waiting for an admin to accept it
     * assigned    = accepted by an admin and handed to an IT engineer
     * in_progress = the engineer is working on it
     */
    public const STATUSES = ['open', 'assigned', 'in_progress', 'on_hold', 'resolved', 'closed', 'cancelled'];
    public const ACTIVE   = ['open', 'assigned', 'in_progress', 'on_hold'];

    public const SUPPORT_TYPES = ['remote' => 'Remote support', 'onsite' => 'On-site visit'];

    /** Target hours to resolve, by priority. */
    public const SLA_HOURS = ['critical' => 4, 'high' => 8, 'medium' => 24, 'low' => 72];

    protected $guarded = [];
    protected $casts = ['resolved_at' => 'datetime', 'accepted_at' => 'datetime', 'scheduled_for' => 'datetime'];

    protected static function booted(): void
    {
        static::creating(function (Ticket $t) {
            $t->ticket_no = 'JMS-' . now()->format('ymd') . '-' . strtoupper(Str::random(4));
        });
    }

    public function user()     { return $this->belongsTo(User::class); }
    public function assignee() { return $this->belongsTo(User::class, 'assigned_to'); }
    public function acceptor() { return $this->belongsTo(User::class, 'accepted_by'); }
    public function comments() { return $this->hasMany(TicketComment::class); }
    public function activity() { return $this->hasMany(ActivityLog::class); }

    /** Submitted, but no admin has accepted it or assigned an engineer yet. */
    public function scopeAwaitingAcceptance($query)
    {
        return $query->where('status', 'open')->whereNull('assigned_to');
    }

    /**
     * Tickets that carry a booked date/time, limited to what this person may see:
     * engineers only see their own assignments, admins and super admins see everything.
     */
    public function scopeBookings($query, User $user)
    {
        return $query->whereNotNull('scheduled_for')
            ->when($user->role === 'it_support', fn ($q) => $q->where('assigned_to', $user->id));
    }

    public function statusLabel(): string   { return ucwords(str_replace('_', ' ', $this->status)); }
    public function priorityLabel(): string { return ucfirst($this->priority); }
    public function supportTypeLabel(): ?string { return self::SUPPORT_TYPES[$this->support_type] ?? null; }

    /** A requester may cancel only while nobody has accepted or been assigned the ticket. */
    public function isCancellable(): bool
    {
        return $this->status === 'open' && $this->assigned_to === null;
    }

    public function isFinished(): bool
    {
        return in_array($this->status, ['resolved', 'closed', 'cancelled']);
    }

    // ---- Schedule ------------------------------------------------------------

    public function isScheduled(): bool
    {
        return $this->scheduled_for !== null;
    }

    /** Human text for "when does the requester need help". */
    public function whenLabel(): string
    {
        return $this->scheduled_for
            ? 'Scheduled for ' . $this->scheduled_for->format('M d, Y h:i A')
            : 'As soon as possible';
    }

    /** Short label for lists while a scheduled ticket is still open. */
    public function scheduledShort(): ?string
    {
        if (! $this->scheduled_for || ! in_array($this->status, self::ACTIVE)) {
            return null;
        }

        return $this->scheduled_for->format('M d, h:i A');
    }

    // ---- SLA ---------------------------------------------------------------

    public function slaDueAt(): CarbonInterface
    {
        // For scheduled tickets the clock starts at the requested time, not when the ticket was typed.
        $start = $this->scheduled_for && $this->scheduled_for->gt($this->created_at) ? $this->scheduled_for : $this->created_at;

        return $start->copy()->addHours(self::SLA_HOURS[$this->priority] ?? 24);
    }

    public function isOverdue(): bool
    {
        return in_array($this->status, ['open', 'assigned', 'in_progress']) && now()->gt($this->slaDueAt());
    }

    /** @return array{0:string,1:string}|null [text, css classes] while the SLA clock is running. */
    public function slaBadge(): ?array
    {
        if (! in_array($this->status, ['open', 'assigned', 'in_progress'])) {
            return null;
        }

        if ($this->scheduled_for && $this->scheduled_for->isFuture()) {
            return ['Scheduled ' . $this->scheduled_for->format('M d, h:i A'), 'text-indigo-600 font-medium'];
        }

        $due  = $this->slaDueAt();
        $span = $due->diffForHumans(['syntax' => CarbonInterface::DIFF_ABSOLUTE, 'short' => true]);

        if (now()->gt($due)) {
            return ["Overdue by {$span}", 'text-red-600 font-semibold'];
        }

        return ["Due in {$span}", now()->diffInHours($due, false) < 2 ? 'text-orange-600 font-medium' : 'text-slate-500'];
    }

    // ---- Google Maps ---------------------------------------------------------

    public function mapsUrl(): ?string
    {
        $loc = trim((string) $this->location);

        if ($loc === '') return null;
        if (preg_match('#^https?://#i', $loc)) return $loc;

        return 'https://www.google.com/maps/search/?api=1&query=' . urlencode($loc);
    }

    public function directionsUrl(): ?string
    {
        $loc = trim((string) $this->location);

        if ($loc === '') return null;
        if (preg_match('#^https?://#i', $loc)) return $loc;

        return 'https://www.google.com/maps/dir/?api=1&destination=' . urlencode($loc);
    }

    // ---- Look & feel -----------------------------------------------------------

    public function statusClasses(): string
    {
        return match ($this->status) {
            'open'        => 'whitespace-nowrap bg-blue-50 text-blue-700 ring-blue-200',
            'assigned'    => 'whitespace-nowrap bg-indigo-50 text-indigo-700 ring-indigo-200',
            'in_progress' => 'whitespace-nowrap bg-amber-50 text-amber-700 ring-amber-200',
            'on_hold'     => 'whitespace-nowrap bg-slate-100 text-slate-600 ring-slate-200',
            'resolved'    => 'whitespace-nowrap bg-emerald-50 text-emerald-700 ring-emerald-200',
            'cancelled'   => 'whitespace-nowrap bg-rose-50 text-rose-700 ring-rose-200',
            default       => 'whitespace-nowrap bg-slate-100 text-slate-500 ring-slate-200',
        };
    }

    public function priorityClasses(): string
    {
        return match ($this->priority) {
            'low'      => 'whitespace-nowrap bg-slate-100 text-slate-600 ring-slate-200',
            'medium'   => 'whitespace-nowrap bg-sky-50 text-sky-700 ring-sky-200',
            'high'     => 'whitespace-nowrap bg-orange-50 text-orange-700 ring-orange-200',
            'critical' => 'whitespace-nowrap bg-red-50 text-red-700 ring-red-200',
        };
    }
}
