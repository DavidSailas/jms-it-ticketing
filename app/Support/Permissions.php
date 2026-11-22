<?php

namespace App\Support;

/**
 * Everything a role can be allowed to do. To add a permission: add it here, then guard the action with
 * `permission:<key>` on its route (or $user->hasPermission('<key>') in code).
 *
 * key => [label, help text, the account types it can be given to]
 * Account types: "admin" = admin level (sees every ticket), "it_support" = IT engineer level (own tickets only).
 */
class Permissions
{
    public const GROUPS = [
        'Tickets' => [
            'tickets.assign'     => ['Accept and assign tickets', 'Accept a new ticket and choose the IT engineer. Includes reassigning.', ['admin']],
            'tickets.edit'       => ['Edit ticket details', 'Change a ticket\'s subject, category, priority, schedule and contact details.', ['admin']],
            'tickets.cancel'     => ['Cancel any ticket', 'Cancel a ticket at any stage, with a reason. Customers can only cancel before an engineer is assigned.', ['admin']],
            'tickets.reschedule' => ['Approve or decline reschedule requests', 'Decide a customer\'s request to move a booking that already has an engineer.', ['admin', 'it_support']],
            'tickets.progress'   => ['Update progress and resolve', 'Set In progress, On hold or Resolved, and choose remote or on-site support.', ['admin', 'it_support']],
        ],
        'People and companies' => [
            'users.manage'       => ['Manage users', 'Add, edit and remove customers and engineers. Super admin accounts are never touched.', ['admin']],
            'companies.manage'   => ['Manage partner companies', 'Add and edit partner companies and their branding.', ['admin']],
        ],
        'Insight' => [
            'reports.view'       => ['View reports', 'Open the reports page and export it.', ['admin']],
        ],
    ];

    /** Only super admins can manage roles; it is never offered as a checkbox. */
    public const SUPER_ONLY = ['roles.manage'];

    public static function all(): array
    {
        $out = [];
        foreach (self::GROUPS as $group => $items) {
            foreach ($items as $key => [$label, $help, $bases]) {
                $out[$key] = ['label' => $label, 'help' => $help, 'bases' => $bases, 'group' => $group];
            }
        }

        return $out;
    }

    public static function keys(): array
    {
        return array_keys(self::all());
    }

    /** Permissions that can be given to this account type. */
    public static function forBase(string $base): array
    {
        return array_keys(array_filter(self::all(), fn ($p) => in_array($base, $p['bases'], true)));
    }

    public static function basesFor(string $key): array
    {
        return self::all()[$key]['bases'] ?? [];
    }

    /** What the built-in roles can do. Also used when an account has no role record yet. */
    public static function defaultsFor(string $base): array
    {
        return match ($base) {
            'super_admin' => self::keys(),
            'admin'       => ['tickets.assign', 'tickets.edit', 'tickets.cancel', 'tickets.reschedule', 'tickets.progress', 'users.manage'],
            'it_support'  => ['tickets.progress', 'tickets.reschedule'],
            default       => [],
        };
    }
}
