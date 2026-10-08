<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Phase 2: screenshots, error photos and log files on tickets.
 */
class AttachmentsTest extends TestCase
{
    use RefreshDatabase;

    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    private Company $acme;
    private Company $globex;
    private User $super;
    private User $acmeAdmin;
    private User $owner;       // Acme staff who submits the tickets
    private User $colleague;   // another Acme staff member
    private User $outsider;    // Globex staff
    private User $engineer;    // JMS engineer

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->acme   = Company::create(['name' => 'Acme Corp']);
        $this->globex = Company::create(['name' => 'Globex Ltd']);

        $this->super     = $this->person('super_admin', null);
        $this->acmeAdmin = $this->person('admin', $this->acme);
        $this->owner     = $this->person('user', $this->acme);
        $this->colleague = $this->person('user', $this->acme);
        $this->outsider  = $this->person('user', $this->globex);
        $this->engineer  = $this->person('it_support', null);
    }

    private function person(string $role, ?Company $company): User
    {
        static $n = 0;
        $n++;

        return User::factory()->create([
            'role' => $role, 'company_id' => $company?->id,
            'username' => "attach{$n}", 'email' => "attach{$n}@example.test",
        ]);
    }

    private function png(string $name = 'error.png'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, base64_decode(self::PNG));
    }

    private function log(string $name = 'db.log'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, "ERROR 1045 (28000): Access denied for user 'root'\n");
    }

    private function payload(array $extra = []): array
    {
        return $extra + [
            'subject' => 'Database will not start', 'category' => 'Database', 'priority' => 'high',
            'description' => 'MySQL stops right after the server boots.', 'when' => 'now',
        ];
    }

    private function submit(User $by, array $files = []): Ticket
    {
        $this->actingAs($by)->post('/tickets', $this->payload($files ? ['files' => $files] : []))->assertSessionHasNoErrors();

        return Ticket::withoutGlobalScopes()->latest('id')->firstOrFail();
    }

    private function url(Ticket $t, TicketAttachment $a): string
    {
        return route('tickets.attachments.show', [$t, $a]);
    }

    // ---- Submitting -------------------------------------------------------------

    public function test_the_form_offers_the_attachment_picker(): void
    {
        $this->actingAs($this->owner)->get('/dashboard')->assertOk()->assertSee('Attach screenshots or logs');
        $this->actingAs($this->acmeAdmin)->get('/tickets/create')->assertOk()->assertSee('Attach screenshots or logs');
    }

    public function test_a_partner_can_submit_a_ticket_with_a_screenshot_and_a_log_file(): void
    {
        $ticket = $this->submit($this->owner, [$this->png(), $this->log()]);

        $this->assertSame(2, $ticket->attachments()->count());

        $shot = $ticket->attachments()->where('original_name', 'error.png')->firstOrFail();
        $this->assertSame('image/png', $shot->mime);
        $this->assertNull($shot->ticket_comment_id);
        $this->assertSame($this->owner->id, $shot->user_id);
        $this->assertStringStartsWith("ticket-attachments/{$ticket->id}/", $shot->path);
        $this->assertStringNotContainsString('error', $shot->path, 'files are stored under a random name');
        Storage::disk('local')->assertExists($shot->path);

        $this->actingAs($this->owner)->get(route('tickets.show', $ticket))->assertOk()
            ->assertSee('Attachments <span', false)->assertSee('error.png')->assertSee('db.log');
    }

    public function test_a_ticket_without_files_still_works(): void
    {
        $ticket = $this->submit($this->owner);

        $this->assertSame(0, $ticket->attachments()->count());
        $this->actingAs($this->owner)->get(route('tickets.show', $ticket))->assertOk()->assertDontSee('Attachments <span', false);
    }

    public function test_unsafe_oversized_and_too_many_files_are_rejected(): void
    {
        $bad = [
            'run.exe'  => UploadedFile::fake()->createWithContent('run.exe', 'MZ'),
            'shell.php' => UploadedFile::fake()->createWithContent('shell.php', '<?php echo 1;'),
            'page.html' => UploadedFile::fake()->createWithContent('page.html', '<script>alert(1)</script>'),
            'huge.log' => UploadedFile::fake()->create('huge.log', 11 * 1024),
        ];

        foreach ($bad as $name => $file) {
            $this->actingAs($this->owner)->post('/tickets', $this->payload(['files' => [$file]]))
                ->assertSessionHasErrors('files.0');
        }

        $six = collect(range(1, 6))->map(fn ($i) => $this->png("shot{$i}.png"))->all();
        $this->actingAs($this->owner)->post('/tickets', $this->payload(['files' => $six]))->assertSessionHasErrors('files');

        $this->assertSame(0, Ticket::withoutGlobalScopes()->count());
        $this->assertSame(0, TicketAttachment::count());
    }

    // ---- Who can open a file ----------------------------------------------------

    public function test_download_rules_follow_the_ticket(): void
    {
        $ticket = $this->submit($this->owner, [$this->png(), $this->log()]);
        $shot = $ticket->attachments()->where('original_name', 'error.png')->firstOrFail();
        $log  = $ticket->attachments()->where('original_name', 'db.log')->firstOrFail();

        // The requester: pictures open in the browser, everything else downloads, nothing is sniffed.
        $r = $this->actingAs($this->owner)->get($this->url($ticket, $shot))->assertOk();
        $this->assertStringContainsString('inline', $r->headers->get('Content-Disposition'));
        $this->assertSame('image/png', $r->headers->get('Content-Type'));
        $r->assertHeader('X-Content-Type-Options', 'nosniff');

        $r = $this->actingAs($this->owner)->get($this->url($ticket, $log))->assertOk();
        $this->assertStringContainsString('attachment', $r->headers->get('Content-Disposition'));
        $this->assertStringContainsString('db.log', $r->headers->get('Content-Disposition'));

        // Admins of the same company and super admins can open it.
        $this->actingAs($this->acmeAdmin)->get($this->url($ticket, $log))->assertOk();
        $this->actingAs($this->super)->get($this->url($ticket, $log))->assertOk();

        // A colleague who did not submit it, another company, and a guest cannot.
        $this->actingAs($this->colleague)->get($this->url($ticket, $log))->assertForbidden();
        $this->actingAs($this->outsider)->get($this->url($ticket, $log))->assertNotFound();
        $this->actingAsGuest()->get($this->url($ticket, $log))->assertRedirect('/login');

        // An engineer only after the ticket is assigned to them.
        $this->actingAs($this->engineer)->get($this->url($ticket, $log))->assertNotFound();
        $ticket->update(['assigned_to' => $this->engineer->id, 'status' => 'assigned']);
        $this->actingAs($this->engineer)->get($this->url($ticket, $log))->assertOk();
    }

    public function test_a_file_cannot_be_opened_through_another_tickets_url(): void
    {
        $mine  = $this->submit($this->owner, [$this->log()]);
        $other = $this->submit($this->owner);

        $log = $mine->attachments()->firstOrFail();

        $this->actingAs($this->owner)->get($this->url($other, $log))->assertNotFound();
    }

    // ---- Replies ----------------------------------------------------------------

    public function test_replies_can_carry_files_and_a_file_alone_is_enough(): void
    {
        $ticket = $this->submit($this->owner);
        $ticket->update(['assigned_to' => $this->engineer->id, 'status' => 'assigned']);

        $this->actingAs($this->engineer)->post(route('tickets.comment', $ticket), [
            'body' => 'Please send the log from the server.', 'files' => [],
        ])->assertSessionHasNoErrors();

        $this->actingAs($this->owner)->post(route('tickets.comment', $ticket), ['files' => [$this->log('server.log')]])
            ->assertSessionHasNoErrors();

        $comment = $ticket->comments()->latest('id')->firstOrFail();
        $this->assertSame('Attached a file.', $comment->body);
        $this->assertSame(1, $comment->attachments()->count());
        $this->assertEquals($comment->id, TicketAttachment::where('original_name', 'server.log')->value('ticket_comment_id'));

        $this->actingAs($this->engineer)->get(route('tickets.show', $ticket))->assertOk()->assertSee('server.log');

        // Still need either text or a file.
        $this->actingAs($this->owner)->post(route('tickets.comment', $ticket), ['body' => ''])->assertSessionHasErrors('body');
    }

    public function test_attachments_on_internal_notes_stay_hidden_from_the_requester(): void
    {
        $ticket = $this->submit($this->owner);
        $ticket->update(['assigned_to' => $this->engineer->id, 'status' => 'assigned']);

        $this->actingAs($this->engineer)->post(route('tickets.comment', $ticket), [
            'body' => 'Checked the server myself.', 'is_internal' => 1, 'files' => [$this->log('trace.log')],
        ])->assertSessionHasNoErrors();

        $note = TicketAttachment::where('original_name', 'trace.log')->firstOrFail();

        $this->actingAs($this->owner)->get($this->url($ticket, $note))->assertForbidden();
        $this->actingAs($this->owner)->get(route('tickets.show', $ticket))->assertOk()->assertDontSee('trace.log');

        $this->actingAs($this->engineer)->get($this->url($ticket, $note))->assertOk();
        $this->actingAs($this->engineer)->get(route('tickets.show', $ticket))->assertSee('trace.log');
    }

    // ---- Removing ---------------------------------------------------------------

    public function test_the_uploader_or_an_admin_can_remove_a_file_and_it_is_deleted_from_disk(): void
    {
        $ticket = $this->submit($this->owner, [$this->png('wrong.png'), $this->log('right.log')]);
        $wrong = $ticket->attachments()->where('original_name', 'wrong.png')->firstOrFail();
        $right = $ticket->attachments()->where('original_name', 'right.log')->firstOrFail();

        // An engineer who neither uploaded it nor is an admin cannot.
        $ticket->update(['assigned_to' => $this->engineer->id, 'status' => 'assigned']);
        $this->actingAs($this->engineer)->delete(route('tickets.attachments.destroy', [$ticket, $wrong]))->assertForbidden();
        Storage::disk('local')->assertExists($wrong->path);

        // The uploader can.
        $this->actingAs($this->owner)->delete(route('tickets.attachments.destroy', [$ticket, $wrong]))->assertRedirect();
        $this->assertDatabaseMissing('ticket_attachments', ['id' => $wrong->id]);
        Storage::disk('local')->assertMissing($wrong->path);

        // So can an admin.
        $this->actingAs($this->acmeAdmin)->delete(route('tickets.attachments.destroy', [$ticket, $right]))->assertRedirect();
        Storage::disk('local')->assertMissing($right->path);
    }
}
