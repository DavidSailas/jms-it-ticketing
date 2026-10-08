<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    public const ROLE_LABELS = [
        'user'        => 'User',
        'it_support'  => 'IT Support',
        'admin'       => 'Admin',
        'super_admin' => 'Super Admin',
    ];

    /** What accounts of JMS's own team show as their company. JMS itself has no company record. */
    public const JMS_NAME = 'JMS One IT';

    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'role',
        'company',
        'company_id',
        'avatar',
        'provider',
        'provider_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    protected static function booted(): void
    {
        // The company name is copied onto the account so every screen can show it without a lookup.
        // JMS's own team (IT Support and Admins with no partner company) is shown as part of JMS.
        static::saving(function (User $u) {
            if (($u->isDirty('company_id') || $u->isDirty('role')) && $u->role !== 'super_admin') {
                $u->company = $u->company_id
                    ? Company::whereKey($u->company_id)->value('name')
                    : (in_array($u->role, ['it_support', 'admin']) ? self::JMS_NAME : null);
            }
        });
    }

    public function companyRecord()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    /**
     * Limit a user query to the signed-in person's own company.
     * Super admins (JMS) are not limited; a JMS admin is limited to JMS's own team (accounts with no
     * partner company); someone else with no company sees nobody.
     */
    public function scopeInMyCompany($query)
    {
        $me = auth()->user();

        if (! $me || $me->role === 'super_admin') {
            return $query;
        }

        if ($me->isJmsAdmin()) {
            $column = $this->qualifyColumn('company_id');

            return $query->where(fn ($q) => $q->whereNull($column)->orWhereIn($column, Company::jmsIds()));
        }

        return $me->company_id
            ? $query->where($this->qualifyColumn('company_id'), $me->company_id)
            : $query->whereRaw('1 = 0');
    }

    public function tickets()
    {
        return $this->hasMany(Ticket::class);
    }

    public function activityLogs()
    {
        return $this->hasMany(ActivityLog::class);
    }

    /**
     * One of JMS's own engineers: IT Support that belongs to no partner company.
     * They can be assigned to any partner's ticket (by a super admin) but only ever see tickets assigned to them.
     */
    public function isJmsEngineer(): bool
    {
        return $this->role === 'it_support' && ! $this->company_id;
    }

    /**
     * True when the account belongs to JMS itself: it is marked as "JMS One IT" and has no partner company,
     * or it is attached to JMS One IT's own company record. An old account that was never placed anywhere
     * (no company at all) is NOT treated as JMS, so it cannot see every partner's tickets by accident.
     */
    public function belongsToJms(): bool
    {
        return $this->company_id
            ? Company::isJmsName($this->companyRecord?->name)
            : Company::isJmsName($this->company);
    }

    /**
     * An admin of JMS One IT itself (an Admin that belongs to JMS, not to a partner company).
     * Works like a super admin on tickets (sees every partner, accepts and assigns JMS engineers),
     * but cannot manage companies, reports or other admins.
     */
    public function isJmsAdmin(): bool
    {
        return $this->role === 'admin' && $this->belongsToJms();
    }

    /** Anyone from JMS who may dispatch JMS engineers to a partner's ticket: super admins and JMS admins. */
    public function canDispatchJms(): bool
    {
        return $this->role === 'super_admin' || $this->isJmsAdmin();
    }

    /** Tickets are logged by people of a partner company: users, and admins who belong to one. JMS's own people do not log tickets. */
    public function canLogTickets(): bool
    {
        return $this->role === 'user' || ($this->role === 'admin' && (bool) $this->company_id);
    }

    public function isStaff(): bool
    {
        return in_array($this->role, ['it_support', 'admin', 'super_admin']);
    }

    public function roleLabel(): string
    {
        if ($this->isJmsAdmin()) {
            return 'JMS Admin';
        }

        return self::ROLE_LABELS[$this->role] ?? ucwords(str_replace('_', ' ', $this->role));
    }

    public function initials(): string
    {
        return \Illuminate\Support\Str::of($this->name)->explode(' ')->filter()
            ->map(fn ($part) => mb_substr($part, 0, 1))->take(2)->implode('');
    }

    public function avatarUrl(): ?string
    {
        return $this->avatar
            ? route('avatar.show', ['user' => $this->id, 'v' => substr(md5($this->avatar), 0, 8)])
            : null;
    }

    /** Data for the notification bell (also served as JSON for live updates). */
    public function notificationFeed(int $limit = 8): array
    {
        return [
            'unread' => $this->unreadNotifications()->count(),
            'items'  => $this->notifications()->latest()->take($limit)->get()
                ->map(fn ($n) => $this->presentNotification($n))->all(),
        ];
    }

    public function presentNotification($n): array
    {
        return [
            'id'      => $n->id,
            'kind'    => $n->data['kind'] ?? 'default',
            'title'   => $n->data['title'] ?? 'Notification',
            'message' => $n->data['message'] ?? '',
            'time'    => $n->created_at->diffForHumans(),
            'ts'      => $n->created_at->timestamp,
            'read'    => $n->read_at !== null,
            'url'     => route('notifications.open', $n->id),
        ];
    }
}
