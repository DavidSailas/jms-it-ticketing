<?php

namespace App\Support;

/**
 * Category, colour and icon of every kind of activity-log entry.
 * Kept in PHP so Tailwind can see the class names.
 */
class ActivityKinds
{
    public const CATEGORIES = [
        'auth'     => 'Sign-ins',
        'security' => 'Security',
        'ticket'   => 'Tickets',
        'profile'  => 'Profile',
    ];

    /** action => [category, classes, icon paths] */
    public static function all(): array
    {
        return [
            'login'            => ['auth',     'bg-emerald-50 text-emerald-600', '<path d="M15 3h3.5A1.5 1.5 0 0 1 20 4.5v15a1.5 1.5 0 0 1-1.5 1.5H15M10 8l4 4-4 4M14 12H3.5"/>'],
            'logout'           => ['auth',     'bg-slate-100 text-slate-600',    '<path d="M9 21H5.5A1.5 1.5 0 0 1 4 19.5v-15A1.5 1.5 0 0 1 5.5 3H9M16 8l4 4-4 4M20 12H9"/>'],
            'login_failed'     => ['security', 'bg-red-50 text-red-600',         '<path d="M12 3l9 16H3L12 3z"/><path d="M12 10v4M12 17h.01"/>'],
            'password_changed' => ['security', 'bg-violet-50 text-violet-600',   '<rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/>'],
            'password_reset'   => ['security', 'bg-amber-50 text-amber-600',     '<rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/>'],
            'profile_updated'  => ['profile',  'bg-sky-50 text-sky-600',         '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>'],
            'avatar_updated'   => ['profile',  'bg-sky-50 text-sky-600',         '<path d="M4 8h3l1.5-2h7L17 8h3a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V9a1 1 0 0 1 1-1z"/><circle cx="12" cy="13.5" r="3.5"/>'],
            'avatar_removed'   => ['profile',  'bg-slate-100 text-slate-600',    '<path d="M4 8h3l1.5-2h7L17 8h3a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V9a1 1 0 0 1 1-1z"/><circle cx="12" cy="13.5" r="3.5"/>'],
            'ticket_created'   => ['ticket',   'bg-blue-50 text-blue-600',       '<circle cx="12" cy="12" r="9"/><path d="M12 8v8M8 12h8"/>'],
            'ticket_updated'   => ['ticket',   'bg-amber-50 text-amber-600',     '<path d="M4 12a8 8 0 0 1 14-5.3L20 9M20 4v5h-5M20 12a8 8 0 0 1-14 5.3L4 15M4 20v-5h5"/>'],
            'ticket_assigned'  => ['ticket',   'bg-indigo-50 text-indigo-600',   '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0M17 8v6M14 11h6"/>'],
            'ticket_resolved'  => ['ticket',   'bg-emerald-50 text-emerald-600', '<circle cx="12" cy="12" r="9"/><path d="M8 12.5l2.7 2.7L16 9.5"/>'],
            'ticket_reopened'  => ['ticket',   'bg-orange-50 text-orange-600',   '<path d="M4 12a8 8 0 0 1 14-5.3L20 9M20 4v5h-5"/>'],
            'ticket_closed'    => ['ticket',   'bg-slate-100 text-slate-600',    '<rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/>'],
            'ticket_cancelled' => ['ticket',   'bg-rose-50 text-rose-600',       '<circle cx="12" cy="12" r="9"/><path d="M9 9l6 6M15 9l-6 6"/>'],
            'comment_added'    => ['ticket',   'bg-sky-50 text-sky-600',         '<path d="M21 12a8 8 0 0 1-11.5 7.2L3 21l1.8-5.5A8 8 0 1 1 21 12z"/>'],
            'note_added'       => ['ticket',   'bg-amber-50 text-amber-600',     '<path d="M7 3h8l4 4v14H7z"/><path d="M15 3v4h4M10 12h6M10 16h6"/>'],
        ];
    }

    public static function category(string $action): string
    {
        return self::all()[$action][0] ?? 'profile';
    }

    /** @return array{0:string,1:string} [classes, icon] */
    public static function look(string $action): array
    {
        $kind = self::all()[$action] ?? ['profile', 'bg-slate-100 text-slate-600', '<circle cx="12" cy="12" r="9"/><path d="M12 8v4M12 16h.01"/>'];

        return [$kind[1], $kind[2]];
    }
}
