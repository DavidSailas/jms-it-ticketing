<?php

namespace App\Mail;

use App\Models\Ticket;
use App\Models\User;
use App\Support\Branding;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

/**
 * The email twin of an in-app notification (see App\Support\Notifier).
 * One email per person, branded with THEIR company's logo and colour (JMS One IT for JMS's own team).
 * It is queued, so sending never slows down the page the person just used.
 */
class TicketActivityMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /** Notification kinds that are also emailed. Everything else stays in the bell unless the payload says `email => true`. */
    public const KINDS = ['new', 'urgent', 'assigned', 'reply', 'resolved', 'security'];

    /** [pill text, background, text colour] for each kind. */
    private const LOOK = [
        'new'      => ['New ticket', '#dbeafe', '#1d4ed8'],
        'urgent'   => ['Critical', '#fee2e2', '#b91c1c'],
        'assigned' => ['Assigned', '#ede9fe', '#6d28d9'],
        'reply'    => ['New reply', '#e0f2fe', '#0369a1'],
        'resolved' => ['Resolved', '#d1fae5', '#047857'],
        'status'   => ['Update', '#fef3c7', '#b45309'],
        'security' => ['Security', '#e2e8f0', '#334155'],
    ];

    public int $tries = 3;

    public function __construct(public User $recipient, public array $payload)
    {
    }

    public function envelope(): Envelope
    {
        $title = (string) ($this->payload['title'] ?? 'Update');
        $no    = $this->payload['ticket_no'] ?? null;

        return new Envelope(subject: $no ? "[{$no}] {$title}" : $title);
    }

    /** Mail apps stack every email about one ticket into a single conversation. */
    public function headers(): Headers
    {
        $id = $this->payload['ticket_id'] ?? null;

        return new Headers(references: $id ? ["ticket-{$id}@" . (parse_url(config('app.url'), PHP_URL_HOST) ?: 'localhost')] : []);
    }

    public function content(): Content
    {
        $company = $this->recipient->companyRecord;
        $ticket  = ! empty($this->payload['ticket_id'])
            ? Ticket::withoutGlobalScopes()->with(['user', 'assignee'])->find($this->payload['ticket_id'])
            : null;

        [$pill, $pillBg, $pillFg] = self::LOOK[$this->payload['kind'] ?? ''] ?? self::LOOK['status'];
        $url = url($this->payload['url'] ?? '/dashboard');

        $data = [
            'brandName'  => $company?->name ?? 'JMS One IT',
            'brandColor' => Branding::isHex($company?->brand_color) ? $company->brand_color : Branding::DEFAULT,
            'logo'       => $company?->logoUrl() ?? asset('images/logo.png'),
            'name'       => strtok($this->recipient->name, ' ') ?: $this->recipient->name,
            'title'      => (string) ($this->payload['title'] ?? ''),
            'lead'       => (string) ($this->payload['message'] ?? ''),
            'pill'       => $pill, 'pillBg' => $pillBg, 'pillFg' => $pillFg,
            'ticket'     => $ticket,
            'url'        => $url,
            'button'     => ($this->payload['kind'] ?? '') === 'security' ? 'Open my profile' : 'View ticket',
            'prefsUrl'   => url(route('profile.edit', absolute: false)),
            'alwaysSent' => ($this->payload['kind'] ?? '') === 'security',
        ];

        return new Content(view: 'emails.ticket-activity', text: 'emails.ticket-activity-text', with: $data);
    }
}
