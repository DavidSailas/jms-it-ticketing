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

    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'role',
        'company',
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

    public function tickets()
    {
        return $this->hasMany(Ticket::class);
    }

    public function activityLogs()
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function isStaff(): bool
    {
        return in_array($this->role, ['it_support', 'admin', 'super_admin']);
    }

    public function roleLabel(): string
    {
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
