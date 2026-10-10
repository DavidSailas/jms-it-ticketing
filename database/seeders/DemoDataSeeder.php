<?php

namespace Database\Seeders;

use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use App\Notifications\TicketActivity;
use App\Support\ActivityKinds;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Local mock data: 3 partner companies, 17 people, 25 tickets in every status, replies, notes, ratings,
 * activity and bell notifications, spread over the last three weeks so the dashboard charts have a shape.
 *
 *     php artisan db:seed --class=DemoDataSeeder
 *
 * Every account uses the password P@ssw0rd123 and an @demo.test email. Never run this on the live site.
 * To start over: php artisan tickets:reset --force   (then run the seeder again after deleting the demo users).
 */
class DemoDataSeeder extends Seeder
{
    private User $dispatcher;

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command->error('Demo data is for local use only. Nothing was added.');

            return;
        }

        if (User::where('email', 'like', '%@demo.test')->exists()) {
            $this->command->warn('Demo data is already there (users with @demo.test emails). Nothing was added.');

            return;
        }

        // The standard accounts (superadmin@jmsoneit.com etc.).
        $this->call(DatabaseSeeder::class);
        $super = User::where('email', 'superadmin@jmsoneit.com')->firstOrFail();

        // ---- JMS's own team -------------------------------------------------------------
        $jmsAdmin = $this->person('Grace Santos', 'grace@demo.test', 'admin', null);
        $marco    = $this->person('Marco Dela Cruz', 'marco@demo.test', 'it_support', null);
        $ana      = $this->person('Ana Villanueva', 'ana@demo.test', 'it_support', null);
        $paolo    = $this->person('Paolo Reyes', 'paolo@demo.test', 'it_support', null);

        // ---- Partner companies ----------------------------------------------------------
        $nw = Company::create(['name' => 'Northwind Trading', 'phone' => '+63 32 555 0101', 'address' => '12 Colon Street, Cebu City']);
        $sr = Company::create(['name' => 'Sunrise Hotel & Resort', 'phone' => '+63 32 555 0202', 'address' => 'Mactan Island, Lapu-Lapu City']);
        $bv = Company::create(['name' => 'Bayview Medical Clinic', 'phone' => '+63 32 555 0303', 'address' => '88 Osmena Blvd, Cebu City']);

        $nwAdmin = $this->person('Liza Navarro', 'liza@demo.test', 'admin', $nw);
        $carlo   = $this->person('Carlo Mendoza', 'carlo@demo.test', 'user', $nw);
        $jenny   = $this->person('Jenny Aquino', 'jenny@demo.test', 'user', $nw);
        $ian     = $this->person('Ian Bautista', 'ian@demo.test', 'it_support', $nw); // Northwind's own IT

        $this->person('Ramon Ocampo', 'ramon@demo.test', 'admin', $sr);
        $bea   = $this->person('Bea Lim', 'bea@demo.test', 'user', $sr);
        $dante = $this->person('Dante Cruz', 'dante@demo.test', 'user', $sr);

        $this->person('Karen Uy', 'karen@demo.test', 'admin', $bv);
        $mia  = $this->person('Mia Tan', 'mia@demo.test', 'user', $bv);
        $jose = $this->person('Jose Ramos', 'jose@demo.test', 'user', $bv);

        $dispatchers = [$super, $jmsAdmin];
        $n = 0;

        // [requester, subject, description, category, priority, status, age in hours, engineer, extras]
        $tickets = [
            // ---- Waiting for acceptance (open, nobody assigned) ----
            [$carlo, 'Main server not responding', "Nobody in the office can open the shared files or the ERP since this morning. The server's lights are on but it does not answer.", 'Server / Infrastructure', 'critical', 'open', 6, null,
                ['phone' => '0917 555 0101', 'location' => 'Head office, 3rd floor server room']],
            [$bea, 'Front desk PC is very slow', 'The reception computer takes about five minutes to start and freezes when we open the booking system.', 'Hardware', 'low', 'open', 30, null,
                ['phone' => '0917 555 0202', 'location' => 'Hotel lobby']],
            [$mia, 'CCTV camera 4 offline in the parking area', 'Camera 4 shows "no signal" on the monitor since last night. The other cameras are fine.', 'CCTV / Surveillance', 'high', 'open', 3, null,
                ['phone' => '0917 555 0303', 'location' => 'Clinic parking area']],
            [$jenny, 'Need access to the Accounting shared drive', 'I was moved to the accounting team and cannot open the Accounting folder on the shared drive.', 'Email / Account Access', 'medium', 'open', 5, null, []],

            // ---- Assigned, not started ----
            [$dante, 'Wi-Fi keeps dropping in the guest wing', 'Guests on floors 2 and 3 complain that the Wi-Fi disconnects every few minutes, mostly in the evening.', 'Network / Internet', 'high', 'assigned', 20, $marco,
                ['support_type' => 'onsite', 'phone' => '0917 555 0202', 'location' => 'Guest wing, floors 2 and 3']],
            [$jose, 'Nightly database backup failed', 'The backup report email says last night\'s database backup failed with a "disk full" error.', 'Database', 'critical', 'assigned', 7, $ana,
                ['support_type' => 'remote', 'phone' => '0917 555 0303']],
            [$carlo, 'Install accounting software on the new laptop', 'We bought a laptop for the new accountant. Please install the accounting software and the shared printer.', 'Software', 'low', 'assigned', 40, $ian,
                ['support_type' => 'remote']],

            // ---- In progress ----
            [$bea, 'POS terminal cannot print receipts', 'The restaurant POS terminal shows an error and no receipts come out. Orders still go to the kitchen.', 'Printer / Peripherals', 'medium', 'in_progress', 28, $paolo,
                ['support_type' => 'onsite', 'phone' => '0917 555 0202', 'location' => 'Restaurant, ground floor',
                 'comments' => [
                     ['eng', 'Hi Bea, I checked the printer cable and it looks fine. I am coming on site tomorrow morning with a replacement printer head.', 3],
                     ['req', 'Thank you. The restaurant opens at 7 AM, can you come before that?', 5],
                     ['eng', 'Yes, I will be there at 6:30 AM.', 6],
                 ]]],
            [$jenny, 'Outlook keeps asking for my password', 'Every few minutes Outlook shows a password window, even after I type the right password.', 'Email / Account Access', 'medium', 'in_progress', 14, $ana,
                ['support_type' => 'remote',
                 'comments' => [
                     ['eng', 'Hello Jenny, could you tell me which version of Outlook you use and whether this started after a password change?', 2],
                     ['req', 'It is Outlook 365 and yes, I changed my password last Friday.', 4],
                     ['note', 'Old saved credentials in Windows Credential Manager. Will clear them in the remote session.', 5],
                 ]]],
            [$mia, 'NVR storage almost full', 'The CCTV recorder says storage is 95% full and the oldest recordings are already being overwritten.', 'CCTV / Surveillance', 'high', 'in_progress', 12, $marco,
                ['support_type' => 'onsite', 'phone' => '0917 555 0303', 'location' => 'Clinic back office',
                 'comments' => [
                     ['eng', 'I will bring a larger hard drive. Please do not delete any recordings in the meantime.', 2],
                 ]]],
            [$dante, 'VPN not connecting from the home office', 'I work from home on Thursdays and the VPN says "connection timed out" since the weekend.', 'Network / Internet', 'medium', 'in_progress', 50, $ana,
                ['support_type' => 'remote']],

            // ---- On hold ----
            [$jose, 'Replace the old UPS in the server room', 'The UPS beeps every few minutes and the battery no longer lasts. We would like it replaced.', 'Hardware', 'low', 'on_hold', 120, $paolo,
                ['support_type' => 'onsite', 'comments' => [['eng', 'The new UPS is on order. I will put this on hold until it arrives next week.', 30]]]],
            [$carlo, 'Firewall rule change request', 'Please allow our new supplier portal through the firewall for the purchasing team.', 'Security', 'medium', 'on_hold', 60, $marco,
                ['support_type' => 'remote', 'comments' => [['eng', 'Waiting for the supplier to send their IP addresses.', 8]]]],

            // ---- Resolved, waiting for the requester to confirm ----
            [$bea, 'Emails to outside addresses are not sending', 'Our emails to Gmail and Yahoo addresses bounce back. Internal emails are fine.', 'Email / Account Access', 'high', 'resolved', 72, $ana,
                ['support_type' => 'remote', 'resolved_after' => 12, 'resolution' => 'The mail server IP was on a spam blocklist. We requested delisting and corrected the SPF record. External emails now send normally.',
                 'comments' => [['eng', 'Found the cause, working on the fix now.', 6]]]],
            [$jenny, 'Printer offline on the 2nd floor', 'The shared printer on the 2nd floor shows offline for everyone.', 'Printer / Peripherals', 'medium', 'resolved', 30, $paolo,
                ['support_type' => 'onsite', 'location' => '2nd floor', 'resolved_after' => 8, 'resolution' => 'The printer had a new IP address from the router. We set a fixed IP and re-added the printer on all PCs.']],

            // ---- Closed (confirmed and rated) ----
            [$mia, 'Slow internet at reception', 'Reception internet is very slow after lunch every day.', 'Network / Internet', 'medium', 'closed', 216, $marco,
                ['support_type' => 'remote', 'resolved_after' => 20, 'resolution' => 'A streaming device was using most of the bandwidth. We limited it on the router.', 'rating' => 5, 'rating_comment' => 'Fast and polite. Thank you!']],
            [$dante, 'Cannot log in to the hotel booking system', 'Three front desk staff get "invalid credentials" in the booking system.', 'Software', 'high', 'closed', 150, $ana,
                ['support_type' => 'remote', 'resolved_after' => 5, 'resolution' => 'The booking software licence had expired. We renewed it and reset the three accounts.', 'rating' => 4, 'rating_comment' => 'Solved the same day.']],
            [$carlo, 'Replace a faulty keyboard', 'The accountant\'s keyboard types double letters.', 'Hardware', 'low', 'closed', 290, $paolo,
                ['support_type' => 'onsite', 'resolved_after' => 30, 'resolution' => 'Replaced with a new keyboard.', 'rating' => 5]],
            [$jose, 'Database restore after an accidental delete', 'A staff member deleted the patient appointments table by mistake.', 'Database', 'critical', 'closed', 360, $ana,
                ['support_type' => 'remote', 'resolved_after' => 3, 'resolution' => 'Restored the table from last night\'s backup and checked that all appointments are back.', 'rating' => 5, 'rating_comment' => 'Lifesaver, thank you.']],
            [$bea, 'Set up a new staff email account', 'Please create an email account for our new receptionist.', 'Email / Account Access', 'low', 'closed', 430, $marco,
                ['support_type' => 'remote', 'resolved_after' => 10, 'resolution' => 'Account created and the login details were given to the manager.', 'rating' => 4]],
            [$jenny, 'CCTV playback is not working', 'We cannot play back yesterday\'s CCTV recordings, the player closes by itself.', 'CCTV / Surveillance', 'medium', 'closed', 480, $marco,
                ['support_type' => 'onsite', 'resolved_after' => 45, 'resolution' => 'Reinstalled the CCTV player and updated the recorder firmware.', 'rating' => 3, 'rating_comment' => 'It took a bit long to get a visit.']],

            // ---- Cancelled ----
            [$mia, 'Request for a new monitor', 'My monitor flickers sometimes. I would like a new one.', 'Hardware', 'low', 'cancelled', 96, null, []],

            // ---- Booked for later (shows on the Schedule) ----
            [$bea, 'Monthly CCTV health check', 'Routine monthly check of all cameras, the recorder and the storage.', 'CCTV / Surveillance', 'low', 'assigned', 26, $marco,
                ['support_type' => 'onsite', 'location' => 'Hotel, all floors', 'scheduled_for' => now()->addDay()->setTime(9, 0)]],
            [$carlo, 'Server room cabling tidy-up', 'Please tidy and label the network cables in the server room.', 'Server / Infrastructure', 'medium', 'assigned', 18, $paolo,
                ['support_type' => 'onsite', 'location' => 'Head office, server room', 'scheduled_for' => now()->addDays(3)->setTime(14, 0)]],
            [$jose, 'Install a new access point in the waiting area', 'The waiting area has weak Wi-Fi. We bought an access point and need it installed.', 'Network / Internet', 'medium', 'open', 9, null,
                ['phone' => '0917 555 0303', 'location' => 'Clinic waiting area', 'scheduled_for' => now()->addDays(5)->setTime(10, 0)]],
        ];

        foreach ($tickets as [$by, $subject, $desc, $category, $priority, $status, $age, $eng, $extra]) {
            $this->dispatcher = $dispatchers[$n++ % 2];
            $this->makeTicket($by, $subject, $desc, $category, $priority, $status, $age, $eng, $extra, $super, $jmsAdmin);
        }

        $this->command->info('Added 3 companies, ' . User::where('email', 'like', '%@demo.test')->count() . ' demo people and ' . count($tickets) . ' tickets.');
        $this->command->table(['Role', 'Email', 'Password'], [
            ['Super admin', 'superadmin@jmsoneit.com', 'P@ssw0rd123'],
            ['JMS admin', 'grace@demo.test', 'P@ssw0rd123'],
            ['JMS engineer', 'marco@demo.test (also ana@, paolo@)', 'P@ssw0rd123'],
            ['Partner admin', 'liza@demo.test (Northwind)', 'P@ssw0rd123'],
            ['Partner staff', 'carlo@demo.test (Northwind)', 'P@ssw0rd123'],
            ['Partner own IT', 'ian@demo.test (Northwind)', 'P@ssw0rd123'],
        ]);
    }

    private function person(string $name, string $email, string $role, ?Company $company): User
    {
        $user = User::create([
            'name'       => $name,
            'username'   => strstr($email, '@', true),
            'email'      => $email,
            'role'       => $role,
            'company_id' => $company?->id,
            'password'   => Hash::make('P@ssw0rd123'),
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }

    private function makeTicket(User $by, string $subject, string $desc, string $category, string $priority, string $status, float $ageHours, ?User $eng, array $extra, User $super, User $jmsAdmin): void
    {
        $created    = now()->subMinutes((int) round($ageHours * 60));
        $accepted   = $eng ? $created->copy()->addMinutes(40) : null;
        $resolvedAt = isset($extra['resolved_after']) ? $created->copy()->addHours($extra['resolved_after']) : null;

        $ticket = Ticket::create([
            'user_id'        => $by->id,
            'company_id'     => $by->company_id,
            'assigned_to'    => $eng?->id,
            'subject'        => $subject,
            'description'    => $desc,
            'category'       => $category,
            'priority'       => $priority,
            'status'         => $status,
            'contact_phone'  => $extra['phone'] ?? null,
            'location'       => $extra['location'] ?? null,
            'support_type'   => $extra['support_type'] ?? null,
            'accepted_by'    => $eng ? $this->dispatcher->id : null,
            'accepted_at'    => $accepted,
            'scheduled_for'  => $extra['scheduled_for'] ?? null,
            'resolution'     => $extra['resolution'] ?? null,
            'resolved_at'    => $resolvedAt,
            'rating'         => $extra['rating'] ?? null,
            'rating_comment' => $extra['rating_comment'] ?? null,
            'created_at'     => $created,
            'updated_at'     => $resolvedAt ? $resolvedAt->copy()->addHours(3) : ($accepted ?? $created),
        ]);

        // Conversation: 'req' = the requester, 'eng' = the engineer, 'note' = an internal staff note.
        foreach ($extra['comments'] ?? [] as [$who, $body, $hoursAfter]) {
            $author = $who === 'req' ? $by : $eng;
            $c = new TicketComment(['ticket_id' => $ticket->id, 'user_id' => $author->id, 'body' => $body, 'is_internal' => $who === 'note']);
            $c->created_at = $c->updated_at = $created->copy()->addMinutes((int) round($hoursAfter * 60));
            $c->save();
            $this->log($author, $who === 'note' ? 'note_added' : 'comment_added', ($who === 'note' ? 'Added an internal note on ' : 'Replied on ') . $ticket->ticket_no, $ticket, $c->created_at);
        }

        // Activity timeline.
        $this->log($by, 'ticket_created', "Submitted {$ticket->ticket_no}: {$subject}", $ticket, $created);
        if ($eng) {
            $this->log($this->dispatcher, 'ticket_assigned', "Accepted {$ticket->ticket_no} and assigned it to {$eng->name}", $ticket, $accepted);
        }
        if ($resolvedAt) {
            $this->log($eng, 'ticket_resolved', "Resolved {$ticket->ticket_no}", $ticket, $resolvedAt);
        }
        if ($status === 'closed') {
            $this->log($by, 'ticket_closed', "Confirmed {$ticket->ticket_no} is fixed and rated it {$ticket->rating}/5", $ticket, $resolvedAt->copy()->addHours(3));
        }
        if ($status === 'cancelled') {
            $this->log($by, 'ticket_cancelled', "Cancelled {$ticket->ticket_no}", $ticket, $created->copy()->addHours(2));
        }

        // Bell notifications for the people who would be told right now.
        $url = route('tickets.show', $ticket, absolute: false);
        if ($status === 'open') {
            foreach ([$super, $jmsAdmin] as $admin) {
                $admin->notify(new TicketActivity([
                    'kind'      => $priority === 'critical' ? 'urgent' : 'new',
                    'title'     => ($priority === 'critical' ? 'Critical ticket ' : 'New ticket ') . $ticket->ticket_no,
                    'message'   => "{$by->name}: {$subject}",
                    'ticket_id' => $ticket->id, 'ticket_no' => $ticket->ticket_no, 'url' => $url,
                ]));
            }
        } elseif ($status === 'assigned' && $eng) {
            $eng->notify(new TicketActivity([
                'kind' => 'assigned', 'title' => 'Ticket assigned to you', 'message' => "{$ticket->ticket_no}: {$subject}",
                'ticket_id' => $ticket->id, 'ticket_no' => $ticket->ticket_no, 'url' => $url,
            ]));
        } elseif ($status === 'resolved') {
            $by->notify(new TicketActivity([
                'kind' => 'resolved', 'title' => 'Your ticket was resolved', 'message' => "{$ticket->ticket_no} is now Resolved.",
                'ticket_id' => $ticket->id, 'ticket_no' => $ticket->ticket_no, 'url' => $url,
            ]));
        }
    }

    private function log(User $user, string $action, string $text, Ticket $ticket, $at): void
    {
        ActivityLog::create([
            'user_id'     => $user->id,
            'category'    => ActivityKinds::category($action),
            'action'      => $action,
            'description' => $text,
            'ticket_id'   => $ticket->id,
            'ip_address'  => '127.0.0.1',
            'user_agent'  => 'Demo data seeder',
            'created_at'  => $at,
        ]);
    }
}
