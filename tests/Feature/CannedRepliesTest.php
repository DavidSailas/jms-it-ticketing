<?php

namespace Tests\Feature;

use App\Models\CannedReply;
use App\Models\Company;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Saved replies and the live SLA clock on the ticket page. */
class CannedRepliesTest extends TestCase
{
    use RefreshDatabase;

    private function setUpTicket(): array
    {
        $company  = Company::create(['name' => 'Acme Corp']);
        $partner  = User::factory()->create(['role' => 'user', 'name' => 'Alice Acme', 'company_id' => $company->id]);
        $engineer = User::factory()->create(['role' => 'it_support', 'name' => 'Ed Engineer']);
        $ticket   = Ticket::create([
            'user_id' => $partner->id, 'company_id' => $company->id, 'assigned_to' => $engineer->id,
            'subject' => 'Printer down', 'description' => 'It will not print.', 'category' => 'Hardware',
            'priority' => 'medium', 'status' => 'assigned',
        ]);

        return [$partner, $engineer, $ticket];
    }

    public function test_staff_see_defaults_with_ticket_details_filled_in(): void
    {
        [, $engineer, $ticket] = $this->setUpTicket();
        $this->assertGreaterThan(0, CannedReply::whereNull('user_id')->count());

        $html = $this->actingAs($engineer)->get(route('tickets.show', $ticket))->assertOk()->getContent();

        $this->assertStringContainsString('Insert a saved reply', $html);
        $this->assertStringContainsString('Alice Acme', $html);
        $this->assertStringNotContainsString('{requester}', $html);
    }

    public function test_partners_never_see_saved_replies_or_the_page(): void
    {
        [$partner, , $ticket] = $this->setUpTicket();

        $this->actingAs($partner)->get(route('tickets.show', $ticket))->assertOk()->assertDontSee('Insert a saved reply');
        $this->actingAs($partner)->get(route('canned-replies.index'))->assertForbidden();
    }

    public function test_staff_manage_only_their_own_replies(): void
    {
        [, $engineer] = $this->setUpTicket();
        $other = User::factory()->create(['role' => 'it_support']);

        $this->actingAs($engineer)->post(route('canned-replies.store'), ['title' => 'Mine', 'body' => 'Hello {requester}'])->assertRedirect();
        $mine = CannedReply::where('title', 'Mine')->firstOrFail();
        $this->assertSame($engineer->id, $mine->user_id);

        $this->actingAs($other)->patch(route('canned-replies.update', $mine), ['title' => 'Hacked', 'body' => 'x'])->assertForbidden();
        $this->actingAs($other)->delete(route('canned-replies.destroy', $mine))->assertForbidden();

        $shared = CannedReply::whereNull('user_id')->firstOrFail();
        $this->actingAs($engineer)->delete(route('canned-replies.destroy', $shared))->assertForbidden();

        $this->actingAs($engineer)->delete(route('canned-replies.destroy', $mine))->assertRedirect();
        $this->assertDatabaseMissing('canned_replies', ['id' => $mine->id]);
    }

    public function test_only_super_admin_can_share_a_reply(): void
    {
        [, $engineer] = $this->setUpTicket();
        $super = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($engineer)->post(route('canned-replies.store'), ['title' => 'Try share', 'body' => 'x', 'shared' => 1]);
        $this->assertNotNull(CannedReply::where('title', 'Try share')->value('user_id'));

        $this->actingAs($super)->post(route('canned-replies.store'), ['title' => 'For all', 'body' => 'x', 'shared' => 1]);
        $this->assertNull(CannedReply::where('title', 'For all')->value('user_id'));
    }

    public function test_sla_clock_runs_only_while_the_ticket_is_active(): void
    {
        [, , $ticket] = $this->setUpTicket();

        $clock = $ticket->slaClock();
        $this->assertNotNull($clock);
        $this->assertGreaterThan($clock['start'], $clock['due']);

        $ticket->update(['status' => 'resolved']);
        $this->assertNull($ticket->fresh()->slaClock());
    }
}
