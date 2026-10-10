<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Super admin can accept and assign a partner's ticket straight from the company page. */
class CompanyQuickAssignTest extends TestCase
{
    use RefreshDatabase;

    private Company $acme;
    private User $super;
    private User $jmsEngineer;
    private Ticket $ticket;

    protected function setUp(): void
    {
        parent::setUp();

        $this->acme        = Company::create(['name' => 'Acme Corp']);
        $this->super       = User::factory()->create(['role' => 'super_admin']);
        $this->jmsEngineer = User::factory()->create(['role' => 'it_support', 'company_id' => null, 'name' => 'Jun JMS Engineer']);
        $owner = User::factory()->create(['role' => 'user', 'company_id' => $this->acme->id]);

        $this->ticket = Ticket::create([
            'user_id' => $owner->id, 'subject' => "Mary's printer is jammed", 'description' => 'Details.', 'category' => 'Hardware',
            'priority' => 'medium', 'status' => 'open',
        ]);
    }

    public function test_the_company_page_offers_assign_but_does_not_list_jms_people_in_the_page(): void
    {
        $this->actingAs($this->super)->get(route('companies.show', $this->acme))
            ->assertOk()->assertSee('Accept &amp; assign', false)->assertSee('Loading engineers', false)
            ->assertDontSee('Jun JMS Engineer');
    }

    public function test_the_assign_button_attribute_survives_quotes_in_the_ticket_subject(): void
    {
        $html = $this->actingAs($this->super)->get(route('companies.show', $this->acme))->getContent();

        $this->assertMatchesRegularExpression('/@click="openAssign\(JSON\.parse\(\'[^"]*\'\)\)"/', $html);
    }

    public function test_the_picker_lists_the_jms_team_for_a_super_admin(): void
    {
        $this->actingAs($this->super)->get(route('tickets.engineer-picker'))
            ->assertOk()->assertSee('JMS support team')->assertSee('Jun JMS Engineer')->assertSee('Available');
    }

    public function test_a_partner_admin_cannot_load_the_picker(): void
    {
        $partnerAdmin = User::factory()->create(['role' => 'admin', 'company_id' => $this->acme->id]);

        $this->actingAs($partnerAdmin)->get(route('tickets.engineer-picker'))->assertForbidden();
    }

    public function test_assigning_from_the_company_page_returns_to_it(): void
    {
        $this->actingAs($this->super)->from(route('companies.show', $this->acme))
            ->post(route('tickets.assign', $this->ticket), [
                'assigned_to' => $this->jmsEngineer->id, 'support_type' => 'remote', 'priority' => 'high', 'note' => 'Call the front desk.',
            ])
            ->assertRedirect(route('companies.show', $this->acme))->assertSessionHasNoErrors();

        $t = $this->ticket->fresh();
        $this->assertSame($this->jmsEngineer->id, (int) $t->assigned_to);
        $this->assertSame('assigned', $t->status);
        $this->assertSame('high', $t->priority);
    }

    public function test_finished_tickets_get_no_assign_button(): void
    {
        $this->ticket->update(['status' => 'closed']);

        $this->actingAs($this->super)->get(route('companies.show', $this->acme))
            ->assertOk()->assertDontSee('Accept &amp; assign', false)->assertDontSee('Loading engineers', false);
    }
}
