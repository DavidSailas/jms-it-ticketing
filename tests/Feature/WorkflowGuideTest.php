<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The Workflow Guide is a super-admin page with a PDF handout. */
class WorkflowGuideTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_sees_the_guide_and_the_sidebar_link(): void
    {
        $super = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($super)->get(route('workflow.index'))
            ->assertOk()->assertSee('How tickets move through JMS One IT')->assertSee('Who can do what')
            ->assertSee('Workflow Guide')->assertSee(route('workflow.pdf'));
    }

    public function test_super_admin_can_download_the_pdf(): void
    {
        $super = User::factory()->create(['role' => 'super_admin']);

        $res = $this->actingAs($super)->get(route('workflow.pdf'))->assertOk();
        $this->assertStringContainsString('application/pdf', $res->headers->get('content-type'));
    }

    public function test_nobody_else_can_open_it(): void
    {
        foreach (['user', 'it_support', 'admin'] as $role) {
            $u = User::factory()->create(['role' => $role]);
            $this->actingAs($u)->get(route('workflow.index'))->assertForbidden();
            $this->actingAs($u)->get(route('workflow.pdf'))->assertForbidden();
        }
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get(route('workflow.index'))->assertRedirect(route('login'));
    }
}
