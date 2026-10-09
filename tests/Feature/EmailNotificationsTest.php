<?php

namespace Tests\Feature;

use App\Mail\TicketActivityMail;
use App\Models\Company;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use App\Support\Notifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Ticket activity is also emailed (queued, branded, opt-out) - the email twin of the in-app bell. */
class EmailNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private Company $acme;
    private User $partner;
    private User $engineer;
    private User $admin;
    private Ticket $ticket;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        $this->acme     = Company::create(['name' => 'Acme', 'brand_color' => '#0f766e']);
        $this->partner  = User::factory()->create(['role' => 'user', 'company_id' => $this->acme->id, 'email' => 'partner@acme.test']);
        $this->admin    = User::factory()->create(['role' => 'super_admin', 'email' => 'boss@jms.test']);
        $this->engineer = User::factory()->create(['role' => 'it_support', 'email' => 'eng@jms.test']);

        $this->ticket = Ticket::create([
            'user_id' => $this->partner->id, 'subject' => 'Printer is offline', 'description' => 'It does not respond.',
            'category' => 'Hardware', 'priority' => 'medium', 'assigned_to' => $this->engineer->id, 'status' => 'assigned',
        ]);
    }

    private function sentTo(string $email): int
    {
        return Mail::queued(TicketActivityMail::class, fn ($m) => $m->hasTo($email))->count();
    }

    public function test_a_new_ticket_emails_the_admins_but_not_the_person_who_raised_it(): void
    {
        Notifier::ticketCreated($this->ticket, $this->partner);

        $this->assertSame(1, $this->sentTo('boss@jms.test'));
        $this->assertSame(0, $this->sentTo('partner@acme.test'));
        $this->assertSame(0, $this->sentTo('eng@jms.test'));
    }

    public function test_assigning_and_resolving_email_the_right_people(): void
    {
        $old = ['status' => 'open', 'priority' => 'medium', 'assigned_to' => null];
        Notifier::ticketUpdated($this->ticket, $this->admin, $old);

        $this->assertSame(1, $this->sentTo('eng@jms.test'));      // "Ticket assigned to you"
        $this->assertSame(1, $this->sentTo('partner@acme.test')); // "An engineer was assigned"

        Mail::fake();
        $this->ticket->update(['status' => 'resolved']);
        Notifier::ticketUpdated($this->ticket, $this->engineer, ['status' => 'in_progress', 'priority' => 'medium', 'assigned_to' => $this->engineer->id]);
        $this->assertSame(1, $this->sentTo('partner@acme.test'));
    }

    public function test_replies_are_emailed_but_internal_notes_never_are(): void
    {
        $reply = TicketComment::create(['ticket_id' => $this->ticket->id, 'user_id' => $this->engineer->id, 'body' => 'Please restart it.', 'is_internal' => false]);
        Notifier::commentAdded($this->ticket, $reply, $this->engineer);
        $this->assertSame(1, $this->sentTo('partner@acme.test'));

        Mail::fake();
        $note = TicketComment::create(['ticket_id' => $this->ticket->id, 'user_id' => $this->admin->id, 'body' => 'Staff only.', 'is_internal' => true]);
        Notifier::commentAdded($this->ticket, $note, $this->admin);
        Mail::assertNothingQueued();
    }

    public function test_plain_status_changes_and_rating_confirmations_stay_in_the_app(): void
    {
        $this->ticket->update(['status' => 'on_hold']);
        Notifier::ticketUpdated($this->ticket, $this->engineer, ['status' => 'in_progress', 'priority' => 'medium', 'assigned_to' => $this->engineer->id]);
        Mail::assertNothingQueued();

        $this->ticket->update(['rating' => 5]);
        Notifier::ticketFeedback($this->ticket, $this->partner);
        Mail::assertNothingQueued();

        // A new schedule is important to the partner, so that one is emailed.
        Notifier::ticketRescheduled($this->ticket, $this->engineer);
        $this->assertSame(1, $this->sentTo('partner@acme.test'));
    }

    public function test_people_who_switched_emails_off_get_none_but_security_emails_always_arrive(): void
    {
        $this->partner->forceFill(['email_notifications' => false])->save();

        Notifier::ticketUpdated($this->ticket->fresh(), $this->admin, ['status' => 'open', 'priority' => 'medium', 'assigned_to' => null]);
        $this->assertSame(0, $this->sentTo('partner@acme.test'));
        $this->assertSame(1, $this->sentTo('eng@jms.test'));   // opted in: still emailed

        Notifier::passwordReset($this->partner->fresh(), $this->admin);
        $this->assertSame(1, $this->sentTo('partner@acme.test'));
    }

    public function test_the_email_is_branded_with_the_recipients_company_and_links_to_the_ticket(): void
    {
        $mail = new TicketActivityMail($this->partner, [
            'kind' => 'resolved', 'title' => 'Your ticket was resolved', 'message' => 'Done.',
            'ticket_id' => $this->ticket->id, 'ticket_no' => $this->ticket->ticket_no,
            'url' => route('tickets.show', $this->ticket, absolute: false),
        ]);

        $mail->assertHasSubject("[{$this->ticket->ticket_no}] Your ticket was resolved");
        $mail->assertSeeInHtml('#0f766e', false);                       // Acme's colour
        $mail->assertSeeInHtml('Acme');
        $mail->assertSeeInHtml('Printer is offline');
        $mail->assertSeeInHtml(url('/tickets/' . $this->ticket->id), false);
        $mail->assertSeeInText('Your ticket was resolved');
        $mail->assertSeeInText(url('/tickets/' . $this->ticket->id));
    }

    public function test_jms_staff_get_the_jms_look_and_a_password_reset_email_links_to_the_profile(): void
    {
        $mail = new TicketActivityMail($this->engineer, ['kind' => 'security', 'title' => 'Your password was reset',
            'message' => 'An administrator reset your password.', 'url' => route('profile.edit', absolute: false)]);

        $mail->assertHasSubject('Your password was reset');
        $mail->assertSeeInHtml('JMS One IT');
        $mail->assertSeeInHtml('Open my profile');
        $mail->assertSeeInHtml('Security emails are always sent.');
    }

    public function test_the_profile_switch_saves_and_is_logged(): void
    {
        $this->actingAs($this->partner)->get(route('profile.edit'))->assertOk()
            ->assertSee('Email notifications')->assertSee('Email me about ticket activity');

        $this->actingAs($this->partner)->patch(route('profile.notifications'), ['email_notifications' => 0])
            ->assertRedirect(route('profile.edit'))->assertSessionHas('status', 'notifications-updated');
        $this->assertFalse($this->partner->fresh()->email_notifications);
        $this->assertDatabaseHas('activity_logs', ['user_id' => $this->partner->id, 'description' => 'Turned ticket emails off']);

        $this->actingAs($this->partner)->patch(route('profile.notifications'), ['email_notifications' => 1]);
        $this->assertTrue($this->partner->fresh()->email_notifications);

        auth()->logout();
        $this->patch(route('profile.notifications'), ['email_notifications' => 0])->assertRedirect(route('login'));
    }
}
