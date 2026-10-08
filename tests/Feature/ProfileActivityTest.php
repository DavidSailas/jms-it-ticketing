<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileActivityTest extends TestCase
{
    use RefreshDatabase;

    private ?Company $company = null;

    /** Everyone is placed in one partner company: tickets are scoped by company, so an unplaced user would see none. */
    private function user(string $role = 'user'): User
    {
        $this->company ??= Company::create(['name' => 'Acme Corp']);

        return User::factory()->create(['role' => $role, 'company_id' => $this->company->id, 'password' => Hash::make('Old-password1')]);
    }

    private function changePassword(User $user, array $data)
    {
        return $this->actingAs($user)->from('/profile')->put('/password', $data);
    }

    public function test_password_rules_give_clear_messages(): void
    {
        $user = $this->user();
        $ok = ['current_password' => 'Old-password1'];

        $this->changePassword($user, $ok + ['password' => 'short', 'password_confirmation' => 'short'])
            ->assertSessionHasErrorsIn('updatePassword', ['password' => 'Your new password must be at least 8 characters long.']);

        $this->changePassword($user, $ok + ['password' => 'alllowercase', 'password_confirmation' => 'alllowercase'])
            ->assertSessionHasErrorsIn('updatePassword', ['password' => 'Your new password still needs an uppercase letter and a number.']);

        $this->changePassword($user, $ok + ['password' => 'Old-password1', 'password_confirmation' => 'Old-password1'])
            ->assertSessionHasErrorsIn('updatePassword', ['password' => 'Your new password must be different from your current one.']);

        $this->changePassword($user, $ok + ['password' => 'New-password1', 'password_confirmation' => 'Other-password1'])
            ->assertSessionHasErrorsIn('updatePassword', ['password_confirmation' => 'The two passwords do not match.']);

        $this->changePassword($user, ['current_password' => 'nope', 'password' => 'New-password1', 'password_confirmation' => 'New-password1'])
            ->assertSessionHasErrorsIn('updatePassword', ['current_password' => 'That is not your current password. Check it and try again.']);

        $this->assertTrue(Hash::check('Old-password1', $user->fresh()->password));
    }

    public function test_changing_password_shows_success_on_the_security_tab_and_is_logged(): void
    {
        $user = $this->user();

        $this->changePassword($user, ['current_password' => 'Old-password1', 'password' => 'New-password1', 'password_confirmation' => 'New-password1'])
            ->assertSessionHasNoErrors()->assertSessionHas('status', 'password-updated');

        $this->assertDatabaseHas('activity_logs', ['user_id' => $user->id, 'action' => 'password_changed', 'category' => 'security']);

        $this->actingAs($user)->withSession(['status' => 'password-updated'])->get('/profile')
            ->assertOk()->assertSee('Password updated')->assertSee("tab: 'security'", false);
    }

    public function test_password_errors_reopen_the_security_tab(): void
    {
        $user = $this->user();

        $this->actingAs($user)->from('/profile')->put('/password', ['current_password' => 'bad'])->assertRedirect('/profile');

        $this->actingAs($user)->get('/profile')->assertSee("tab: 'security'", false)
            ->assertSee("We couldn't update your password");
    }

    public function test_sign_in_sign_out_and_failed_attempts_are_logged(): void
    {
        $user = $this->user();
        $user->forceFill(['username' => 'jdoe'])->save();

        $this->post('/login', ['login' => 'jdoe', 'password' => 'wrong'])->assertSessionHasErrors();
        $this->assertDatabaseHas('activity_logs', ['user_id' => $user->id, 'action' => 'login_failed', 'category' => 'security']);

        $this->post('/login', ['login' => 'jdoe', 'password' => 'Old-password1']);
        $this->assertDatabaseHas('activity_logs', ['user_id' => $user->id, 'action' => 'login', 'category' => 'auth']);

        $this->post('/logout');
        $this->assertDatabaseHas('activity_logs', ['user_id' => $user->id, 'action' => 'logout']);
    }

    public function test_ticket_actions_and_profile_changes_are_logged(): void
    {
        $partner = $this->user();
        $staff = $this->user('admin');                 // admins accept and manage tickets
        $engineer = $this->user('it_support');

        $this->actingAs($partner)->post('/tickets', [
            'subject' => 'Printer is jammed', 'category' => 'Hardware', 'priority' => 'low', 'description' => 'Paper stuck in tray 2.', 'when' => 'now',
        ]);
        $ticket = Ticket::first();
        $this->assertDatabaseHas('activity_logs', ['user_id' => $partner->id, 'action' => 'ticket_created', 'ticket_id' => $ticket->id]);

        $this->actingAs($staff)->patch("/tickets/{$ticket->id}", ['status' => 'in_progress', 'priority' => 'high', 'assigned_to' => $engineer->id]);
        $log = ActivityLog::where('user_id', $staff->id)->where('action', 'ticket_updated')->firstOrFail();
        $this->assertStringContainsString('status to In Progress', $log->description);
        $this->assertStringContainsString('priority to High', $log->description);

        // No change, no log entry.
        $this->actingAs($staff)->patch("/tickets/{$ticket->id}", ['status' => 'in_progress', 'priority' => 'high', 'assigned_to' => $engineer->id]);
        $this->assertSame(1, ActivityLog::where('user_id', $staff->id)->where('action', 'ticket_updated')->count());

        $this->actingAs($staff)->post("/tickets/{$ticket->id}/comments", ['body' => 'Looking into it', 'is_internal' => 1]);
        $this->assertDatabaseHas('activity_logs', ['user_id' => $staff->id, 'action' => 'note_added']);

        $this->actingAs($partner)->patch('/profile', ['name' => 'New Name', 'email' => $partner->email]);
        $this->assertDatabaseHas('activity_logs', ['user_id' => $partner->id, 'action' => 'profile_updated', 'description' => 'Updated your name']);
    }

    public function test_activity_log_paginates_10_per_page_and_filters(): void
    {
        $user = $this->user();
        $other = $this->user();

        foreach (range(1, 155) as $i) {
            ActivityLog::create(['user_id' => $user->id, 'category' => 'auth', 'action' => 'login', 'description' => "Signed in #$i"]);
        }
        ActivityLog::create(['user_id' => $user->id, 'category' => 'security', 'action' => 'password_changed', 'description' => 'Changed your password']);
        ActivityLog::create(['user_id' => $other->id, 'category' => 'auth', 'action' => 'login', 'description' => 'SOMEONE ELSES ENTRY']);

        $page1 = $this->actingAs($user)->get('/profile?logs_page=1')->assertOk();
        $page1->assertSee("tab: 'activity'", false)->assertSee('Next page')->assertSee('Showing')->assertSee('156')
            ->assertDontSee('SOMEONE ELSES ENTRY');
        $this->assertSame(10, substr_count($page1->getContent(), 'class="flex items-start gap-4 px-5 py-4'));

        $last = $this->actingAs($user)->get('/profile?logs_page=16')->assertOk();
        $this->assertSame(6, substr_count($last->getContent(), 'class="flex items-start gap-4 px-5 py-4'));

        $this->actingAs($user)->get('/profile?type=security')->assertOk()
            ->assertSee('Changed your password')->assertDontSee('Signed in #1')->assertDontSee('Next page');

        $this->actingAs($user)->get('/profile?type=bogus')->assertOk();
        $this->actingAs($user)->get('/profile?type[]=x')->assertOk();
    }

    public function test_empty_activity_log_has_a_friendly_state(): void
    {
        $this->actingAs($this->user())->get('/profile?type=all')->assertOk()->assertSee('No activity yet');
    }

    public function test_device_is_read_from_the_browser_string(): void
    {
        $log = new ActivityLog(['user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36 Edg/120.0']);
        $this->assertSame('Edge on Windows', $log->device());
        $this->assertNull((new ActivityLog())->device());
    }
}
