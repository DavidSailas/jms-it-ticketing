<?php

namespace App\Console\Commands;

use App\Mail\TicketActivityMail;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/** php artisan mail:test someone@example.com : checks the SMTP settings by sending the real, branded email right now. */
class SendTestEmail extends Command
{
    protected $signature = 'mail:test {email : Who should receive the test email}';

    protected $description = 'Send a sample ticket email to check your mail settings';

    public function handle(): int
    {
        $to = (string) $this->argument('email');
        $mailer = config('mail.default');

        if ($mailer === 'log') {
            $this->warn('MAIL_MAILER is "log": emails are only written to storage/logs, nothing is delivered. Set MAIL_MAILER=smtp in .env first.');
        }

        // Use a real person if one has this address (so the company branding is right), otherwise a stand-in.
        $user = User::where('email', $to)->first() ?? new User(['name' => 'there', 'email' => $to]);
        $ticket = Ticket::withoutGlobalScopes()->latest('id')->first();

        $payload = [
            'kind'      => 'reply',
            'title'     => 'Test email from JMS One IT',
            'message'   => 'If you can read this, your mail settings work and ticket emails will reach you.',
            'url'       => $ticket ? route('tickets.show', $ticket, absolute: false) : '/dashboard',
            'ticket_id' => $ticket?->id,
            'ticket_no' => $ticket?->ticket_no,
        ];

        try {
            Mail::to($to)->sendNow(new TicketActivityMail($user, $payload));
        } catch (\Throwable $e) {
            $this->error('Could not send: ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->info("Sent through the \"{$mailer}\" mailer to {$to}. Links in the email use APP_URL (" . config('app.url') . ').');

        return self::SUCCESS;
    }
}
