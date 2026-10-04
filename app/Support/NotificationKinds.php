<?php

namespace App\Support;

/**
 * Look (colour + icon) of each notification kind.
 * Kept in PHP so Tailwind can see the class names.
 */
class NotificationKinds
{
    public static function all(): array
    {
        return [
            'new'      => ['bg-blue-50 text-blue-600',      '<circle cx="12" cy="12" r="9"/><path d="M12 8v8M8 12h8"/>'],
            'urgent'   => ['bg-red-50 text-red-600',        '<path d="M12 3l9 16H3L12 3z"/><path d="M12 10v4M12 17h.01"/>'],
            'assigned' => ['bg-violet-50 text-violet-600',  '<circle cx="10" cy="8" r="3.5"/><path d="M3 20a7 7 0 0 1 14 0M19 8v6M16 11h6"/>'],
            'status'   => ['bg-amber-50 text-amber-600',    '<path d="M4 12a8 8 0 0 1 14-5.3L20 9M20 4v5h-5M20 12a8 8 0 0 1-14 5.3L4 15M4 20v-5h5"/>'],
            'resolved' => ['bg-emerald-50 text-emerald-600', '<circle cx="12" cy="12" r="9"/><path d="M8.5 12.5l2.5 2.5 4.5-5"/>'],
            'reply'    => ['bg-sky-50 text-sky-600',        '<path d="M21 12a8 8 0 0 1-11.5 7.2L3 21l1.8-5.5A8 8 0 1 1 21 12z"/>'],
            'note'     => ['bg-amber-50 text-amber-600',    '<path d="M7 3h8l4 4v14H7z"/><path d="M15 3v4h4M10 12h6M10 16h6"/>'],
            'priority' => ['bg-orange-50 text-orange-600',  '<path d="M5 21V4M5 4h11l-2 4 2 4H5"/>'],
            'security' => ['bg-slate-100 text-slate-600',   '<rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/>'],
            'default'  => ['bg-slate-100 text-slate-600',   '<circle cx="12" cy="12" r="9"/><path d="M12 8v4M12 16h.01"/>'],
        ];
    }

    public static function for(?string $kind): array
    {
        return self::all()[$kind] ?? self::all()['default'];
    }
}
