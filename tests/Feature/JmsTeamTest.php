<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * JMS One IT is our own company. Its people are filed in two ways (no company at all, or attached to the
 * "JMS One IT" company record) and both must count as the same JMS team: in the assign list and on its company page.
 */
class JmsTeamTest extends TestCase
{
    use RefreshDatabase;

    private Company $jms;
    private Company $acme;
    private User $super;
    private User $jmsAdmin;
    private User $engineerNoCompany;   // e.g. a demo or older account
    private User $engineerOnRecord;    // attached to the JMS One IT company record
    private User $acmeAdmin;
    private User $acmeUser;
    private User $acmeEngineer;
    private Ticket $ticket;

    protected function setUp(): void
    {
        parent::setUp();

        $this->jms  = Company::create(['name' => 'JMS One IT']);
        $this->acme = Company::create(['name' => 'Acme Corp']);

        $this->super             = $this->person('super_admin', null, 'Boss Super');
        $this->jmsAdmin          = $this->person('admin', null, 'Grace JMS Admin');
        $this->engineerNoCompany = $this->person('it_support', null, 'Marco No Company');
        $this->engineerOnRecord  = $this->person('it_support', $this->jms, 'David On Record');
        $this->acmeAdmin         = $this->person('admin', $this->acme, 'Acme Admin');
        $this->acmeUser          = $this->person('user', $this->acme, 'Acme Staff');
        $this->acmeEngineer      = $this->person('it_support', $this->acme, 'Acme Own Engineer');

        $this->ticket = Ticket::create([
            'user_id' => $this->acmeUser->id, 'subject' => 'CCTV camera offline', 'description' => 'No signal since last night.',
            'category' => 'CCTV / Surveillance', 'priority' => 'high', 'status' => 'open',
        ]);
    }

    private function person(string $role, ?Company $company, string $name): User
    {
        static $n = 0;
        $n++;

        return User::factory()->create([
            'name' => $name, 'role' => $role, 'company_id' => $company?->id,
            'username' => 'jmsteam' . $n, 'email' => "jmsteam{$n}@example.test",
        ]);
    }

    public function test_both_kinds_of_jms_engineer_are_recognised(): void
    {
        $this->assertTrue($this->engineerNoCompany->fresh()->isJmsEngineer());
        $this->assertTrue($this->engineerOnRecord->fresh()->isJmsEngineer());
        $this->assertFalse($this->acmeEngineer->fresh()->isJmsEngineer());

        $ids = User::jmsEngineers()->pluck('id')->all();
        $this->assertEqualsCanonicalizing([$this->engineerNoCompany->id, $this->engineerOnRecord->id], $ids);
    }

    public function test_the_assign_list_offers_every_jms_engineer_and_no_partner_it(): void
    {
        $this->actingAs($this->super)->get(route('tickets.show', $this->ticket))->assertOk()
            ->assertSee('JMS support team')
            ->assertSee('Marco No Company')
            ->assertSee('David On Record')
            ->assertDontSee('Company IT team')
            ->assertDontSee('Acme Own Engineer');
    }

    public function test_an_engineer_filed_under_the_jms_record_can_be_assigned_and_locks_the_partner_out(): void
    {
        $this->actingAs($this->jmsAdmin)->post(route('tickets.assign', $this->ticket), [
            'assigned_to' => $this->engineerOnRecord->id, 'support_type' => 'onsite', 'priority' => 'high',
        ])->assertSessionHasNoErrors();

        $this->assertSame($this->engineerOnRecord->id, (int) Ticket::withoutGlobalScopes()->find($this->ticket->id)->assigned_to);

        // JMS has it now, so the partner's admin cannot take it back.
        $this->actingAs($this->acmeAdmin)->post(route('tickets.assign', $this->ticket), [
            'assigned_to' => $this->acmeEngineer->id, 'support_type' => 'onsite', 'priority' => 'high',
        ])->assertSessionHas('error');
        $this->assertSame($this->engineerOnRecord->id, (int) Ticket::withoutGlobalScopes()->find($this->ticket->id)->assigned_to);
    }

    public function test_the_jms_company_page_lists_the_whole_jms_team_and_nobody_else(): void
    {
        $this->actingAs($this->super)->get(route('companies.show', $this->jms))->assertOk()
            ->assertSee('Our company')
            ->assertSee('Boss Super')
            ->assertSee('Grace JMS Admin')
            ->assertSee('Marco No Company')
            ->assertSee('David On Record')
            // (Acme's requester name is not checked: the hidden "waiting for acceptance" data on every admin page carries it.)
            ->assertDontSee('Acme Own Engineer')
            ->assertDontSee('Acme Admin');
    }

    public function test_the_jms_company_page_shows_team_figures_instead_of_a_ticket_list(): void
    {
        $this->actingAs($this->super)->get(route('companies.show', $this->jms))->assertOk()
            ->assertSee('Team members')->assertSee('IT engineers')->assertSee('Resolved this month')
            ->assertDontSee('company-tickets-heading')
            ->assertDontSee('Delete company');

        $page = $this->actingAs($this->super)->get(route('companies.show', $this->jms));
        $this->assertSame(4, (int) $page->viewData('counts')->sum());     // super admin, JMS admin and the two engineers
    }

    public function test_the_admins_and_it_tabs_count_the_jms_team_correctly(): void
    {
        $counts = $this->actingAs($this->super)->get(route('companies.show', $this->jms))->viewData('counts');

        $this->assertSame(2, (int) $counts['admin']);        // super admin + JMS admin
        $this->assertSame(2, (int) $counts['it_support']);   // both kinds of engineer
        $this->assertArrayNotHasKey('super_admin', $counts->all());

        $this->actingAs($this->super)->get(route('companies.show', ['company' => $this->jms, 'role' => 'admin']))->assertOk()
            ->assertSee('Boss Super')->assertSee('Grace JMS Admin')->assertDontSee('Marco No Company');
        $this->actingAs($this->super)->get(route('companies.show', ['company' => $this->jms, 'role' => 'it_support']))->assertOk()
            ->assertSee('Marco No Company')->assertSee('David On Record')->assertDontSee('Grace JMS Admin');
    }

    public function test_a_partner_company_page_is_unchanged(): void
    {
        $this->actingAs($this->super)->get(route('companies.show', $this->acme))->assertOk()
            ->assertSee('Acme Own Engineer')->assertSee('Acme Staff')
            // (The logged-in name 'Boss Super' is always in the page header, so it is not checked here.)
            ->assertDontSee('Marco No Company')->assertDontSee('Grace JMS Admin')
            ->assertSee('company-tickets-heading')->assertSee('Delete company')->assertDontSee('Our company');
    }

    public function test_the_company_list_counts_the_jms_team_on_its_own_card(): void
    {
        $companies = $this->actingAs($this->super)->get(route('companies.index'))->assertOk()->viewData('companies');
        $card = $companies->firstWhere('id', $this->jms->id);

        $this->assertSame(2, (int) $card->admins_count);
        $this->assertSame(2, (int) $card->engineers_count);
        $this->assertSame(0, (int) $card->users_count);

        $acme = $companies->firstWhere('id', $this->acme->id);
        $this->assertSame(1, (int) $acme->admins_count);
        $this->assertSame(1, (int) $acme->engineers_count);
    }
}
