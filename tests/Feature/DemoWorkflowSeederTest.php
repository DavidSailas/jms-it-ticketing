<?php

namespace Tests\Feature;

use App\Models\Ticket;
use Database\Seeders\DemoWorkflowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoWorkflowSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_one_open_demo_ticket_and_can_be_rerun(): void
    {
        $this->seed(DemoWorkflowSeeder::class);
        $first = Ticket::withoutGlobalScopes()->where('subject', 'like', '[DEMO]%')->firstOrFail();
        $this->assertSame('open', $first->status);
        $this->assertNull($first->assigned_to);

        // Pretend the demo was played, then reset it.
        $first->update(['status' => 'closed']);
        $this->seed(DemoWorkflowSeeder::class);

        $demo = Ticket::withoutGlobalScopes()->where('subject', 'like', '[DEMO]%')->get();
        $this->assertCount(1, $demo);
        $this->assertSame('open', $demo->first()->status);
        $this->assertNotSame($first->id, $demo->first()->id);
    }
}
