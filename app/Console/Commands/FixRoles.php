<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * One-off clean-up for databases created before the roles were split into "JMS staff" and "Customers".
 *
 * Admins and IT Engineers now always belong to JMS One IT; only Customers belong to a partner company.
 * Older data may have admins or engineers filed under a partner company. This command lists them first
 * and changes nothing until you add --apply.
 *
 *   php artisan jms:fix-roles                                 preview only
 *   php artisan jms:fix-roles --keep-staff=admin@jmsoneit.com,itsupport@jmsoneit.com --apply
 *
 * Anyone listed in --keep-staff stays an Admin / IT Engineer and is moved to JMS One IT.
 * Every other admin or engineer filed under a partner company becomes a Customer of that company.
 */
class FixRoles extends Command
{
    protected $signature = 'jms:fix-roles {--keep-staff= : Comma-separated emails that are really JMS staff} {--apply : Save the changes (without it nothing is changed)}';

    protected $description = 'Move partner-side admins and IT engineers to Customers, and file JMS staff under JMS One IT';

    public function handle(): int
    {
        $keep = collect(explode(',', (string) $this->option('keep-staff')))
            ->map(fn ($e) => strtolower(trim($e)))->filter()->all();

        $jmsIds = Company::all()->filter->isJms()->pluck('id')->all();

        $rows = User::whereIn('role', ['admin', 'it_support'])->whereNotNull('company_id')
            ->whereNotIn('company_id', $jmsIds)->orderBy('email')->get();

        $plan = $rows->map(fn (User $u) => [
            $u->email, $u->role, $u->company, in_array(strtolower($u->email), $keep, true) ? 'stays staff, moves to JMS One IT' : 'becomes Customer',
        ]);

        if ($plan->isEmpty()) {
            $this->info('Nothing to fix: no admin or IT engineer is filed under a partner company.');
        } else {
            $this->table(['Email', 'Role now', 'Company now', 'Change'], $plan->all());
        }

        if (! $this->option('apply')) {
            $this->warn('Preview only. Add --apply to save these changes.');

            return self::SUCCESS;
        }

        foreach ($rows as $u) {
            if (! in_array(strtolower($u->email), $keep, true)) {
                $u->role = 'user';
            }
            $u->save();   // the User model files staff under JMS One IT and keeps customers in their company
        }

        // Staff already on the JMS company record or with no company: make sure they show as JMS One IT.
        User::whereIn('role', User::STAFF_ROLES)->get()->each->save();

        $this->info('Done. ' . $rows->count() . ' account(s) updated.');

        return self::SUCCESS;
    }
}
