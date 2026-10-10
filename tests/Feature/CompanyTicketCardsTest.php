<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The company page lists that company's tickets as a List or a Board, filtered by the summary tiles. */
class CompanyTicketCardsTest extends TestCase
{
    use RefreshDatabase;

    private Company $acme;
    private User $super;
    private User $partner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->acme    = Company::create(['name' => 'Acme Corp']);
        $this->super   = User::factory()->create(['role' => 'super_admin']);
        $this->partner = User::factory()->create(['role' => 'user', 'company_id' => $this->acme->id]);
    }

    private function ticket(string $subject, string $status, ?Company $company = null): Ticket
    {
        $owner = $company ? User::factory()->create(['role' => 'user', 'company_id' => $company->id]) : $this->partner;

        return Ticket::create([
            'user_id' => $owner->id, 'subject' => $subject, 'description' => 'Details here.', 'category' => 'Hardware',
            'priority' => 'medium', 'status' => $status,
        ]);
    }

    public function test_the_company_page_lists_its_tickets_linking_to_each_one(): void
    {
        $t = $this->ticket('Printer is jammed', 'open');

        $this->actingAs($this->super)->get(route('companies.show', $this->acme))
            ->assertOk()->assertSee('Printer is jammed')->assertSee($t->ticket_no)->assertSee(route('tickets.show', $t));
    }

    public function test_only_this_companys_tickets_appear(): void
    {
        $other = Company::create(['name' => 'Other Co']);
        $this->ticket('Acme problem', 'open');
        $foreign = $this->ticket('Other company problem', 'open', $other);

        // The admin layout also embeds the global "waiting for acceptance" feed as JSON, so the raw page can
        // mention other companies' tickets. What matters is that this company's list does not link to them.
        $this->actingAs($this->super)->get(route('companies.show', $this->acme))
            ->assertSee('Acme problem')->assertDontSee(route('tickets.show', $foreign));
    }

    public function test_the_summary_tiles_filter_the_cards(): void
    {
        $this->ticket('Still being worked on', 'in_progress');
        $this->ticket('Already fixed', 'resolved');
        $this->ticket('Fixed and confirmed', 'closed');

        $page = fn (array $q = []) => $this->actingAs($this->super)->get(route('companies.show', ['company' => $this->acme] + $q));

        $page()->assertSee('Still being worked on')->assertSee('Already fixed')->assertSee('Fixed and confirmed');
        $page(['tickets' => 'active'])->assertSee('Still being worked on')->assertDontSee('Already fixed')->assertDontSee('Fixed and confirmed');
        $page(['tickets' => 'done'])->assertDontSee('Still being worked on')->assertSee('Already fixed')->assertSee('Fixed and confirmed');
        $page(['tickets' => 'nonsense'])->assertOk()->assertSee('Still being worked on'); // unknown filter falls back to all
    }

    public function test_a_company_with_no_tickets_shows_a_friendly_empty_state(): void
    {
        $this->actingAs($this->super)->get(route('companies.show', $this->acme))->assertOk()->assertSee('No tickets yet');
    }

    public function test_the_list_is_paged_ten_at_a_time(): void
    {
        foreach (range(1, 12) as $i) {
            $this->ticket("Numbered ticket {$i}", 'open');
        }

        $this->actingAs($this->super)->get(route('companies.show', $this->acme))->assertOk()->assertSee('12 tickets')
            ->assertSee('Numbered ticket 12')->assertDontSee('Numbered ticket 1</a>', false);
    }

    public function test_the_board_view_groups_tickets_by_status_and_keeps_closed_ones_in_the_list(): void
    {
        $this->ticket('Waiting for a fix', 'open');
        $this->ticket('Engineer is on it', 'in_progress');
        $this->ticket('Fixed yesterday', 'resolved')->update(['resolved_at' => now()->subDay()]);
        $this->ticket('Fixed last year', 'resolved')->update(['resolved_at' => now()->subYear()]);
        $this->ticket('Long since closed', 'closed');

        $this->actingAs($this->super)->get(route('companies.show', ['company' => $this->acme, 'tv' => 'board']))
            ->assertOk()->assertSee('In progress')->assertSee('On hold')
            ->assertSee('Waiting for a fix')->assertSee('Engineer is on it')->assertSee('Fixed yesterday')
            ->assertDontSee('Fixed last year')->assertDontSee('Long since closed');

        $this->actingAs($this->super)->get(route('companies.show', $this->acme))->assertSee('Long since closed');
    }

    public function test_the_filter_tiles_work_in_the_board_too(): void
    {
        $this->ticket('Active one', 'in_progress');
        $this->ticket('Done one', 'resolved')->update(['resolved_at' => now()]);

        $this->actingAs($this->super)->get(route('companies.show', ['company' => $this->acme, 'tv' => 'board', 'tickets' => 'active']))
            ->assertSee('Active one')->assertDontSee('Done one');
    }

    public function test_only_super_admins_can_open_the_company_page(): void
    {
        $this->actingAs($this->partner)->get(route('companies.show', $this->acme))->assertForbidden();
    }
}
