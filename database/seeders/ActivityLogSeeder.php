<?php

namespace Database\Seeders;

use App\Models\ActivityLog;
use App\Models\Ticket;
use App\Models\User;
use App\Support\ActivityKinds;
use Illuminate\Database\Seeder;

/**
 * Optional demo data so the Activity log on My Profile has enough rows to page through.
 *
 *     php artisan db:seed --class=ActivityLogSeeder
 */
class ActivityLogSeeder extends Seeder
{
    public function run(): void
    {
        $agents = [
            ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36', '203.177.14.22'],
            ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36 Edg/130.0', '203.177.14.22'],
            ['Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1', '112.198.66.9'],
            ['Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Safari/605.1.15', '49.145.201.77'],
        ];

        $users = User::whereIn('email', ['user@jmsoneit.com', 'itsupport@jmsoneit.com'])->get();

        foreach ($users as $user) {
            $tickets = $user->isStaff()
                ? Ticket::where('assigned_to', $user->id)->get()
                : $user->tickets()->get();
            $at = now()->subMinutes(20);

            for ($i = 0; $i < 155; $i++) {
                $roll = $i % 11;
                $ticket = $tickets->isNotEmpty() ? $tickets->random() : null;

                [$action, $text] = match (true) {
                    $roll === 0, $roll === 6 => ['login', 'Signed in'],
                    $roll === 1, $roll === 7 => ['logout', 'Signed out'],
                    $roll === 2 && $ticket   => ['ticket_created', "Submitted {$ticket->ticket_no}: {$ticket->subject}"],
                    $roll === 3 && $ticket   => ['comment_added', "Replied on {$ticket->ticket_no}"],
                    $roll === 4 && $ticket   => ['ticket_updated', "Changed {$ticket->ticket_no}: status to In Progress"],
                    $roll === 5              => ['login_failed', 'Failed sign-in attempt (wrong password)'],
                    $roll === 8              => ['password_changed', 'Changed your password'],
                    $roll === 9              => ['profile_updated', 'Updated your name'],
                    default                  => ['login', 'Signed in'],
                };

                [$ua, $ip] = $agents[$i % count($agents)];
                $at = $at->copy()->subMinutes(random_int(35, 540));

                ActivityLog::create([
                    'user_id'     => $user->id,
                    'category'    => ActivityKinds::category($action),
                    'action'      => $action,
                    'description' => $text,
                    'ticket_id'   => str_starts_with($action, 'ticket') || $action === 'comment_added' ? $ticket?->id : null,
                    'ip_address'  => $ip,
                    'user_agent'  => $ua,
                    'created_at'  => $at,
                ]);
            }
        }
    }
}
