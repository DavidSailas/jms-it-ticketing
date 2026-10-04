<?php

namespace App\Console\Commands;

use App\Models\Ticket;
use Illuminate\Console\Command;

class RemoveDemoTickets extends Command
{
    protected $signature = 'tickets:remove-demo';
    protected $description = 'Delete the 3 mock tickets that older versions of the seeder created';

    public function handle(): int
    {
        $count = Ticket::whereIn('subject', ['Cannot connect to office Wi-Fi', 'Printer not printing', 'Email password reset'])->delete();
        $this->info("Removed {$count} demo ticket(s).");

        return self::SUCCESS;
    }
}
