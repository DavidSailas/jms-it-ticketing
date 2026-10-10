<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The Kanban board: who sees what, and which moves are allowed. */
class TicketBoardTest extends TestCase
{
    use RefreshDatabase;

    private Company $acme;
    private User $partner;
    private User $engineer;
    private User $otherEngineer;
    private User $super;

    protected function setUp(): void
    {
        parent::setUp();

        $this->acme          = Company::create(['name' => 'Acme Corp']);
        $this->partner       = User::factory()->create(['role' => 'user', 'company_id' => $this->acme->id]);
        $this->engineer      = User::factory()->create(['role' => 'it_support', 'name' => 'Ed Engineer']);
        $this->otherEngineer = User::factory()->create(['role' => 'it_support', 'name' => 'Olga Other']);
        $this->super         = User::factory()->create(['role' => 'super_admin']);
    }

    private function ticket(array $attrs = []): Ticket
    {
        return Ticket::create($attrs + [
            'user_id' => $this->partner->id, 'subject' => 'Wi-Fi is down', 'description' => 'Nothing connects.',
            'category' => 'Hardware', 'priority' => 'medium', 'status' => 'assigned', 'assigned_to' => $this->engineer->id,
        ]);
    }

    private function move(User $as, Ticket $t, array $body)
    {
        return $this->actingAs($as)->postJson(route('tickets.board.move', $t), $body);
    }

    public function test_partners_cannot_open_or_use_the_board(): void
    {
        $t = $this->ticket();

        $this->actingAs($this->partner)->get(route('tickets.board'))->assertForbidden();
        $this->move($this->partner, $t, ['status' => 'in_progress'])->assertForbidden();
    }

    public function test_engineers_see_only_their_tickets_and_admins_see_all(): void
    {
        $mine   = $this->ticket(['subject' => 'Mine to fix']);
        $theirs = $this->ticket(['subject' => 'Belongs to Olga', 'assigned_to' => $this->otherEngineer->id]);

        $this->actingAs($this->engineer)->get(route('tickets.board'))->assertOk()
            ->assertSee('Mine to fix')->assertDontSee('Belongs to Olga');

        $this->actingAs($this->super)->get(route('tickets.board'))->assertOk()
            ->assertSee('Mine to fix')->assertSee('Belongs to Olga');
    }

    public function test_engineer_can_move_their_ticket_to_in_progress(): void
    {
        $t = $this->ticket();

        $this->move($this->engineer, $t, ['status' => 'in_progress'])
            ->assertOk()->assertJsonPath('card.status', 'in_progress');

        $this->assertSame('in_progress', $t->fresh()->status);
    }

    public function test_engineer_cannot_move_someone_elses_ticket(): void
    {
        $t = $this->ticket(['assigned_to' => $this->otherEngineer->id]);

        // JMS engineers cannot even look the ticket up (404); a partner company's own engineer reaches our check (403).
        $status = $this->move($this->engineer, $t, ['status' => 'in_progress'])->getStatusCode();
        $this->assertContains($status, [403, 404]);
        $this->assertSame('assigned', $t->withoutGlobalScopes()->find($t->id)->status);
    }

    public function test_unassigned_tickets_cannot_be_dragged(): void
    {
        $t = $this->ticket(['status' => 'open', 'assigned_to' => null]);

        $this->move($this->super, $t, ['status' => 'in_progress'])->assertStatus(422);
        $this->assertSame('open', $t->fresh()->status);
    }

    public function test_resolving_needs_a_description_of_the_fix(): void
    {
        $t = $this->ticket(['status' => 'in_progress']);

        $this->move($this->engineer, $t, ['status' => 'resolved'])->assertStatus(422);
        $this->move($this->engineer, $t, ['status' => 'resolved', 'resolution' => 'short'])->assertStatus(422);
        $this->assertSame('in_progress', $t->fresh()->status);

        $this->move($this->engineer, $t, ['status' => 'resolved', 'resolution' => 'Replaced the faulty access point.'])->assertOk();
        $fresh = $t->fresh();
        $this->assertSame('resolved', $fresh->status);
        $this->assertNotNull($fresh->resolved_at);
    }

    public function test_finished_tickets_and_bad_statuses_are_refused(): void
    {
        $done = $this->ticket(['status' => 'resolved', 'resolved_at' => now()]);
        $this->move($this->engineer, $done, ['status' => 'in_progress'])->assertStatus(422);

        $live = $this->ticket();
        $this->move($this->engineer, $live, ['status' => 'closed'])->assertStatus(422);
        $this->move($this->engineer, $live, ['status' => 'open'])->assertStatus(422);
        $this->assertSame('assigned', $live->fresh()->status);
    }

    public function test_old_resolved_tickets_drop_off_the_board(): void
    {
        $this->ticket(['subject' => 'Fresh fix', 'status' => 'resolved', 'resolved_at' => now()->subDay()]);
        $this->ticket(['subject' => 'Ancient fix', 'status' => 'resolved', 'resolved_at' => now()->subDays(30)]);

        $this->actingAs($this->super)->get(route('tickets.board'))->assertSee('Fresh fix')->assertDontSee('Ancient fix');
    }

    public function test_the_ticket_list_links_to_the_board_for_staff_only(): void
    {
        $this->actingAs($this->engineer)->get(route('tickets.index'))->assertOk()->assertSee(route('tickets.board'));
        $this->actingAs($this->partner)->get(route('tickets.index'))->assertOk()->assertDontSee(route('tickets.board'));
    }

    public function test_requests_from_a_partner_admin_are_marked(): void
    {
        $acmeAdmin = User::factory()->create(['role' => 'admin', 'company_id' => $this->acme->id]);
        $this->ticket(['user_id' => $acmeAdmin->id, 'subject' => 'Boss request']);

        $this->actingAs($this->super)->get(route('tickets.index'))->assertOk()->assertSee('Company admin');
        $this->ticket(['subject' => 'Ordinary request']); // from a regular partner user: not marked

        $cards = $this->actingAs($this->super)->getJson(route('tickets.board.cards'))->json('cards');
        $this->assertSame([true, false], collect($cards)->sortByDesc(fn ($c) => $c['subject'] === 'Boss request')->pluck('fromAdmin')->values()->all());
    }
}
