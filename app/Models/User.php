<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    public const ROLE_LABELS = [
        'user'        => 'Customer',
        'it_support'  => 'IT Engineer',
        'admin'       => 'Admin',
        'super_admin' => 'Super Admin',
    ];

    /**
     * JMS One IT's own people. They never belong to a partner company.
     * Everyone else (role "user", shown as Customer) belongs to exactly one partner company.
     */
    public const STAFF_ROLES = ['it_support', 'admin', 'super_admin'];

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
        'role_id',
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
            'email_notifications' => 'boolean',
            'password' => 'hashed',
        ];
    }

    protected static function booted(): void
    {
        // The company name is copied onto the account so every screen can show it without a lookup.
        // JMS's own people (IT Engineers, Admins, Super Admins) always belong to JMS One IT and never to a partner company.
        static::saving(function (User $u) {
            // Moved to another account type: the custom role no longer fits, so fall back to that type's standard role.
            if ($u->role_id && $u->isDirty('role') && ! $u->isDirty('role_id')) {
                $u->role_id = null;
            }

            if (in_array($u->role, self::STAFF_ROLES, true)) {
                $u->company_id = null;
                $u->company    = self::JMS_NAME;

                return;
            }

            if ($u->isDirty('company_id') || $u->isDirty('role')) {
                $u->company = $u->company_id ? Company::whereKey($u->company_id)->value('name') : null;
            }
        });
    }

    public function companyRecord()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    /**
     * Roles this person may see and manage on the Users page (and find with the search).
     * Super admin -> customers, IT engineers and admins.
     * Admin       -> customers and IT engineers.
     * Everyone else manages nobody.
     */
    public function manageableRoles(): array
    {
        return match ($this->role) {
            'super_admin' => ['user', 'it_support', 'admin'],
            'admin'       => ['user', 'it_support'],
            default       => [],
        };
    }

    /**
     * Limit a user query to the people the signed-in person may look up.
     * Super admins and admins (JMS) are not limited. Engineers and customers look nobody up.
     */
    public function scopeInMyCompany($query)
    {
        $me = auth()->user();

        if (! $me || in_array($me->role, ['super_admin', 'admin'], true)) {
            return $query;
        }

        return $query->whereRaw('1 = 0');
    }

    public function tickets()
    {
        return $this->hasMany(Ticket::class);
    }

    public function activityLogs()
    {
        return $this->hasMany(ActivityLog::class);
    }

    /** One of JMS's own IT Engineers. They only ever see tickets an Admin or Super Admin assigned to them. */
    public function isJmsEngineer(): bool
    {
        return $this->role === 'it_support';
    }

    /** JMS's own IT Engineers. */
    public function scopeJmsEngineers($query)
    {
        return $query->where('role', 'it_support');
    }

    /** Everyone who works for JMS itself: super admins, admins and IT engineers. Never a partner's people. */
    public function scopeJmsTeam($query)
    {
        return $query->whereIn('role', self::STAFF_ROLES);
    }

    public function assignedTickets()
    {
        return $this->hasMany(Ticket::class, 'assigned_to');
    }

    /** True for JMS One IT's own people. Partner customers never are. */
    public function belongsToJms(): bool
    {
        return $this->isStaff();
    }

    /** An Admin of JMS One IT. Works like a super admin on tickets (sees every partner, accepts and assigns engineers). */
    public function isJmsAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /** The role record that decides what this person may do (their own, or the standard one for their account type). */
    public function roleDefinition(): ?Role
    {
        return rescue(function () {
            $role = $this->role_id ? Role::map()->get($this->role_id) : null;

            // A custom role only counts while it still matches the account type.
            return $role && $role->base_role === $this->role ? $role : Role::forSlug($this->role);
        }, null, false);
    }

    /** Super admins can do everything. Everyone else can do what their role allows, within their account type. */
    public function hasPermission(string $permission): bool
    {
        if ($this->role === 'super_admin') {
            return true;
        }
        if (in_array($permission, \App\Support\Permissions::SUPER_ONLY, true)) {
            return false;
        }

        $role    = $this->roleDefinition();
        $granted = $role ? ($role->permissions ?? []) : \App\Support\Permissions::defaultsFor($this->role);

        return in_array($permission, $granted, true)
            && in_array($this->role, \App\Support\Permissions::basesFor($permission), true);
    }

    /** Anyone who may accept a ticket and assign an IT Engineer to it (by default admins and super admins). */
    public function canDispatchJms(): bool
    {
        return $this->hasPermission('tickets.assign');
    }

    /** Tickets are logged by the customers of a partner company. JMS's own people do not log tickets. */
    public function canLogTickets(): bool
    {
        return $this->role === 'user';
    }

    public function isStaff(): bool
    {
        return in_array($this->role, ['it_support', 'admin', 'super_admin']);
    }

    public function roleLabel(): string
    {
        return $this->roleDefinition()?->name ?? self::ROLE_LABELS[$this->role] ?? ucwords(str_replace('_', ' ', $this->role));
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
