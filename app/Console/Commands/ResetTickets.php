<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;

class ResetTickets extends Command
{
    protected $signature = 'tickets:reset {--all-logs : Also clear the whole activity log (sign-ins etc.), not just ticket entries} {--force : Skip the confirmation question}';
    protected $description = 'Delete every ticket (and its replies, notifications, activity) and restart the ticket IDs at 1. Users are NOT touched.';

    public function handle(): int
    {
        $count = DB::table('tickets')->count();

        if (! $this->option('force') && ! $this->confirm("This permanently deletes all {$count} ticket(s), their replies and notifications. Users stay. Continue?")) {
            $this->warn('Cancelled. Nothing was deleted.');

            return self::FAILURE;
        }

        Schema::disableForeignKeyConstraints();

        // truncate() also resets the auto-increment counter, so the next ticket gets id 1.
        if (Schema::hasTable('ticket_attachments')) {
            DB::table('ticket_attachments')->truncate();
        }
        Storage::disk('local')->deleteDirectory('ticket-attachments');

        DB::table('ticket_comments')->truncate();
        DB::table('tickets')->truncate();
        DB::table('notifications')->truncate();

        if ($this->option('all-logs')) {
            DB::table('activity_logs')->truncate();
        } else {
            DB::table('activity_logs')->whereNotNull('ticket_id')->delete();
        }

        Schema::enableForeignKeyConstraints();

        $this->info("Done. Removed {$count} ticket(s). The next ticket will be #1. Users were not changed.");

        return self::SUCCESS;
    }
}
