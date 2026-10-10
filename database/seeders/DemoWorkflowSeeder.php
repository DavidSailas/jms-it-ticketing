<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * One fresh, untouched ticket to walk through the whole workflow in a live demo.
 *
 *     php artisan db:seed --class=DemoWorkflowSeeder
 *
 * Run it again before each demo: the previous [DEMO] ticket is removed and a new Open one is created.
 * The people and the "Demo Company" are created once and reused. Local use only.
 */
class DemoWorkflowSeeder extends Seeder
{
    private const PASSWORD = 'P@ssw0rd123';

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command->error('The workflow demo is for local use only. Nothing was added.');

            return;
        }

        $company = Company::firstOrCreate(['name' => 'Demo Company'], ['phone' => '+63 32 555 0000', 'address' => 'Cebu City']);

        $staff = $this->person('Dina Staff', 'demo.staff@demo.test', 'user', $company->id);
        $admin = $this->person('Dario Admin', 'demo.admin@demo.test', 'admin', $company->id);
        $it    = $this->person('Ivy IT Support', 'demo.it@demo.test', 'it_support', $company->id);
        $jms   = $this->person('Jun JMS Engineer', 'demo.jms@demo.test', 'it_support', null);

        // Start from a clean slate: remove earlier demo tickets (their replies and history go with them).
        Ticket::withoutGlobalScopes()->where('subject', 'like', '[DEMO]%')->get()->each->delete();

        $ticket = Ticket::create([
            'user_id'     => $staff->id,
            'subject'     => '[DEMO] Outlook keeps asking for my password',
            'description' => 'Since this morning Outlook asks for my password every few minutes and I cannot send email. Other apps work fine.',
            'category'    => 'Email / Account Access',
            'priority'    => 'medium',
            'status'      => 'open',
        ]);

        $this->command->info("Demo ticket {$ticket->ticket_no} is Open and ready.");
        $this->command->table(['Role in the story', 'Email', 'Password'], [
            ['1. Reports the problem (staff)', $staff->email, self::PASSWORD],
            ['2. Accepts and assigns (company admin)', $admin->email, self::PASSWORD],
            ['3. Fixes it (company IT support)', $it->email, self::PASSWORD],
            ['Path B: JMS engineer', $jms->email, self::PASSWORD],
            ['Path B: accepts and assigns', 'superadmin@jmsoneit.com', self::PASSWORD],
        ]);
    }

    private function person(string $name, string $email, string $role, ?int $companyId): User
    {
        $user = User::updateOrCreate(['email' => $email], [
            'name' => $name, 'username' => strstr($email, '@', true), 'role' => $role,
            'company_id' => $companyId, 'password' => Hash::make(self::PASSWORD),
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }
}
