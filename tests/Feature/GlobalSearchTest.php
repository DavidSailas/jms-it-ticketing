<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Ctrl+K search: finds what you may open, and nothing else. */
class GlobalSearchTest extends TestCase
{
    use RefreshDatabase;

    private Company $acme;
    private Company $globex;
    private User $acmeUser;
    private User $acmeAdmin;
    private User $globexUser;
    private User $engineer;
    private User $super;

    protected function setUp(): void
    {
        parent::setUp();

        $this->acme   = Company::create(['name' => 'Acme Corp']);
        $this->globex = Company::create(['name' => 'Globex']);

        $this->acmeUser   = User::factory()->create(['role' => 'user', 'name' => 'Alice Acme', 'company_id' => $this->acme->id]);
        $this->acmeAdmin  = User::factory()->create(['role' => 'admin', 'name' => 'Adam Acme', 'company_id' => $this->acme->id]);
        $this->globexUser = User::factory()->create(['role' => 'user', 'name' => 'Gina Globex', 'company_id' => $this->globex->id]);
        $this->engineer   = User::factory()->create(['role' => 'it_support', 'name' => 'Ed Engineer']);
        $this->super      = User::factory()->create(['role' => 'super_admin', 'name' => 'Sue Super']);
    }

    private function ticket(User $by, string $subject, array $attrs = []): Ticket
    {
        return Ticket::create($attrs + [
            'user_id' => $by->id, 'subject' => $subject, 'description' => 'Details about the problem.',
            'category' => 'Hardware', 'priority' => 'medium',
        ]);
    }

    private function search(User $as, string $q)
    {
        return $this->actingAs($as)->getJson(route('search', ['q' => $q]));
    }

    public function test_guests_cannot_search(): void
    {
        $this->getJson(route('search', ['q' => 'printer']))->assertUnauthorized();
    }

    public function test_short_or_empty_searches_return_nothing(): void
    {
        $this->ticket($this->acmeUser, 'Printer is offline');

        foreach (['', 'p', ' ', '%%', '__', '\\'] as $q) {
            $this->search($this->super, $q)->assertOk()
                ->assertJsonPath('tickets', [])->assertJsonPath('people', [])->assertJsonPath('companies', []);
        }
    }

    public function test_a_partner_only_finds_their_own_tickets(): void
    {
        $mine = $this->ticket($this->acmeUser, 'Printer is offline');
        $this->ticket($this->acmeAdmin, 'Printer jam upstairs');                 // same company, not theirs
        $this->ticket($this->globexUser, 'Printer on fire');                      // another company

        $res = $this->search($this->acmeUser, 'printer')->assertOk();
        $res->assertJsonCount(1, 'tickets')->assertJsonPath('tickets.0.id', $mine->id)
            ->assertJsonPath('tickets.0.url', "/tickets/{$mine->id}");
    }

    public function test_an_engineer_only_finds_tickets_assigned_to_them(): void
    {
        $assigned = $this->ticket($this->acmeUser, 'Router keeps dropping', ['assigned_to' => $this->engineer->id, 'status' => 'assigned']);
        $this->ticket($this->acmeUser, 'Router in the lobby');                    // not assigned to anyone

        $this->search($this->engineer, 'router')->assertOk()
            ->assertJsonCount(1, 'tickets')->assertJsonPath('tickets.0.id', $assigned->id);
    }

    public function test_a_company_admin_stays_inside_their_company_for_tickets_and_people(): void
    {
        $this->ticket($this->acmeUser, 'VPN is slow');
        $this->ticket($this->globexUser, 'VPN is down');

        $res = $this->search($this->acmeAdmin, 'vpn')->assertOk();
        $res->assertJsonCount(1, 'tickets');

        // People: their own company's people, never another company's, and never super admins.
        $names = collect($this->search($this->acmeAdmin, 'acme')->json('people'))->pluck('title');
        $this->assertTrue($names->contains('Alice Acme'));
        $this->assertFalse($names->contains('Sue Super'));
        $this->assertSame([], $this->search($this->acmeAdmin, 'globex')->json('people'));
        $this->search($this->acmeAdmin, 'acme')->assertJsonPath('companies', []);
    }

    public function test_partners_and_engineers_never_get_people_or_companies(): void
    {
        foreach ([$this->acmeUser, $this->engineer] as $who) {
            $this->search($who, 'acme')->assertOk()->assertJsonPath('people', [])->assertJsonPath('companies', []);
            $this->search($who, 'globex')->assertOk()->assertJsonPath('people', [])->assertJsonPath('companies', []);
        }
    }

    public function test_a_super_admin_finds_everything_across_companies(): void
    {
        $this->ticket($this->globexUser, 'Warehouse scanner broken');

        $this->search($this->super, 'globex')->assertOk()
            ->assertJsonPath('companies.0.title', 'Globex')
            ->assertJsonPath('companies.0.url', "/companies/{$this->globex->id}")
            ->assertJsonPath('people.0.title', 'Gina Globex')
            ->assertJsonPath('people.0.url', "/users/{$this->globexUser->id}")
            ->assertJsonCount(1, 'tickets');   // found through the requester's company name
    }

    public function test_an_exact_ticket_number_comes_first_and_carries_status_details(): void
    {
        $older = $this->ticket($this->acmeUser, 'Mentions ticket number in text');
        $target = $this->ticket($this->acmeUser, 'Something else entirely', ['status' => 'in_progress']);

        $res = $this->search($this->super, $target->ticket_no)->assertOk();
        $res->assertJsonPath('tickets.0.id', $target->id)->assertJsonPath('tickets.0.no', $target->ticket_no)
            ->assertJsonPath('tickets.0.status_label', 'In Progress')->assertJsonPath('tickets.0.color', '#f59e0b');
    }

    public function test_wildcards_are_treated_as_plain_text_not_as_match_everything(): void
    {
        $this->ticket($this->acmeUser, 'Printer is offline');

        $this->search($this->super, 'pr%')->assertOk()->assertJsonCount(1, 'tickets');   // "%" is dropped: "pr"
        $this->search($this->super, '%_%')->assertOk()->assertJsonPath('tickets', []);
    }

    public function test_results_are_limited(): void
    {
        foreach (range(1, 12) as $i) {
            $this->ticket($this->acmeUser, "Monitor flickers {$i}");
        }

        $this->search($this->super, 'monitor')->assertOk()->assertJsonCount(6, 'tickets');
    }

    public function test_every_role_gets_the_search_button_and_only_its_own_shortcuts(): void
    {
        $partner = $this->actingAs($this->acmeUser)->get('/dashboard')->assertOk();
        $partner->assertSee('commandPalette(', false)->assertSee('open-search', false)->assertSee('My profile');
        $partner->assertDontSee('/companies', false)->assertDontSee('/reports', false)->assertDontSee('Search users for');

        $super = $this->actingAs($this->super)->get('/dashboard')->assertOk();
        $super->assertSee('Companies')->assertSee('Reports')->assertSee('Search users for')->assertSee('Waiting for acceptance');
    }
}
