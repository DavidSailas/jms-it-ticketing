<?php

namespace App\Models;

use App\Support\ActivityKinds;
use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** [classes, svg paths] for the row icon. */
    public function look(): array
    {
        return ActivityKinds::look($this->action);
    }

    /** "Today, 8:59 PM" / "Yesterday, 3:10 PM" / "Oct 01, 2026, 9:00 AM". */
    public function whenLabel(): string
    {
        $t = $this->created_at;

        return match (true) {
            $t->isToday()     => 'Today, ' . $t->format('h:i A'),
            $t->isYesterday() => 'Yesterday, ' . $t->format('h:i A'),
            default           => $t->format('M d, Y, h:i A'),
        };
    }

    /** "Chrome on Windows", read from the browser's user-agent string. */
    public function device(): ?string
    {
        $ua = (string) $this->user_agent;

        if ($ua === '') {
            return null;
        }

        $browser = match (true) {
            str_contains($ua, 'Edg/')                                  => 'Edge',
            str_contains($ua, 'OPR/') || str_contains($ua, 'Opera')    => 'Opera',
            str_contains($ua, 'Firefox/')                              => 'Firefox',
            str_contains($ua, 'Chrome/') || str_contains($ua, 'CriOS') => 'Chrome',
            str_contains($ua, 'Safari/')                               => 'Safari',
            default                                                    => 'Browser',
        };

        $os = match (true) {
            str_contains($ua, 'Windows')                                  => 'Windows',
            str_contains($ua, 'iPhone') || str_contains($ua, 'iPad')      => 'iOS',
            str_contains($ua, 'Android')                                  => 'Android',
            str_contains($ua, 'Mac OS X') || str_contains($ua, 'Macintosh') => 'macOS',
            str_contains($ua, 'Linux')                                    => 'Linux',
            default                                                       => null,
        };

        return $os ? "$browser on $os" : $browser;
    }
}
