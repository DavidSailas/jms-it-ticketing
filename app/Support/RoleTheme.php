<?php

namespace App\Support;

/**
 * How the app shell (sidebar, top bar, role badges) looks for each role.
 *
 * Admin       -> light shell with violet accents ("Admin Console")
 * Super Admin -> dark navy shell with gold accents ("Super Admin Console")
 * Others      -> the standard light blue shell
 *
 * Kept in PHP (under app/Support) so Tailwind can see every class name.
 */
class RoleTheme
{
    public const SHIELD = '<path d="M12 3l8 3v6c0 4.5-3.2 8.2-8 9-4.8-.8-8-4.5-8-9V6l8-3z"/><path d="M9 12l2 2 4-4"/>';
    public const CROWN  = '<path d="M3 8l4.5 4L12 5l4.5 7L21 8l-2 11H5L3 8z"/><path d="M5 19h14"/>';

    public static function for(?string $role): array
    {
        $standard = [
            'role'        => $role,
            'headings'    => false,
            'groups'      => [],
            'icon'        => null,
            'tagline'     => null,
            'tag'         => null,
            'aside'       => 'border-slate-200 bg-white',
            'divider'     => 'border-slate-100',
            'logoCard'    => '',
            'navActive'   => 'bg-brand-50 text-brand-700 border-brand-600',
            'navIdle'     => 'text-slate-600 hover:bg-slate-100 border-transparent',
            'iconActive'  => 'text-brand-600',
            'iconIdle'    => 'text-slate-400',
            'heading'     => 'text-slate-400',
            'count'       => 'bg-brand-600 text-white',
            'pill'        => 'bg-brand-50 text-brand-700',
            'headerPill'  => '',
            'accent'      => null,
            'footerCard'  => '',
            'footerIcon'  => '',
            'footerTitle' => '',
            'footerText'  => '',
        ];

        return match ($role) {
            'admin' => array_merge($standard, [
                'headings'    => true,
                'groups'      => ['main' => 'Service desk', 'manage' => 'People'],
                'icon'        => self::SHIELD,
                'label'       => 'Admin',
                'tagline'     => 'Triage tickets & manage your team',
                'tag'         => 'bg-violet-50 text-violet-700 ring-1 ring-inset ring-violet-200',
                'navActive'   => 'bg-violet-50 text-violet-700 border-violet-600',
                'iconActive'  => 'text-violet-600',
                'heading'     => 'text-violet-400',
                'count'       => 'bg-violet-600 text-white',
                'pill'        => 'bg-violet-50 text-violet-700 ring-1 ring-inset ring-violet-200',
                'headerPill'  => 'bg-violet-50 text-violet-700 ring-1 ring-inset ring-violet-200',
                'accent'      => 'bg-gradient-to-r from-violet-500 via-indigo-500 to-brand-500',
                'footerCard'  => 'bg-violet-50/70 ring-1 ring-inset ring-violet-100',
                'footerIcon'  => 'bg-violet-600 text-white',
                'footerTitle' => 'text-violet-900',
                'footerText'  => 'text-violet-700/80',
            ]),
            'super_admin' => array_merge($standard, [
                'headings'    => true,
                'groups'      => ['main' => 'Operations', 'manage' => 'Administration'],
                'icon'        => self::CROWN,
                'label'       => 'Super Admin',
                'tagline'     => 'Full system access',
                'tag'         => 'bg-amber-400/15 text-amber-300 ring-1 ring-inset ring-amber-400/40',
                'aside'       => 'border-brand-800 bg-brand-900',
                'divider'     => 'border-white/10',
                'logoCard'    => 'rounded-2xl bg-white p-1.5 shadow-lg shadow-black/30',
                'navActive'   => 'bg-white/10 text-white border-amber-400',
                'navIdle'     => 'text-brand-100 hover:bg-white/5 hover:text-white border-transparent',
                'iconActive'  => 'text-amber-300',
                'iconIdle'    => 'text-brand-200/70',
                'heading'     => 'text-brand-200/60',
                'count'       => 'bg-amber-400 text-brand-900',
                'pill'        => 'bg-amber-50 text-amber-800 ring-1 ring-inset ring-amber-300',
                'headerPill'  => 'bg-gradient-to-r from-brand-900 to-brand-700 text-amber-300 ring-1 ring-inset ring-amber-400/40',
                'accent'      => 'bg-gradient-to-r from-amber-300 via-amber-400 to-amber-500',
                'footerCard'  => 'bg-white/5 ring-1 ring-inset ring-white/10',
                'footerIcon'  => 'bg-gradient-to-br from-amber-300 to-amber-500 text-brand-900',
                'footerTitle' => 'text-white',
                'footerText'  => 'text-brand-200',
            ]),
            default => $standard,
        };
    }
}
