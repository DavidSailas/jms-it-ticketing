<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserEditTest extends TestCase
{
    use RefreshDatabase;

    private function u(string $email): User { return User::where('email', $email)->firstOrFail(); }

    private function payload(User $u, array $over = []): array
    {
        return array_merge(['name' => $u->name, 'username' => $u->username, 'email' => $u->email, 'company' => $u->company, 'company_id' => (string) ($u->company_id ?? ''), 'role' => $u->role], $over);
    }

    public function test_admin_can_edit_a_user_and_the_change_is_logged(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = $this->u('admin@jmsoneit.com');
        $user  = $this->u('user@jmsoneit.com');

        $this->actingAs($admin)->patch("/users/{$user->id}", $this->payload($user, [
            'name' => 'Renamed Person', 'username' => 'Renamed.Person', 'email' => 'Renamed@Partner.com', 'company' => 'New Co', 'role' => 'it_support',
        ]))->assertSessionHasNoErrors()->assertSessionHas('success');

        $user->refresh();
        $this->assertSame('Renamed Person', $user->name);
        $this->assertSame('renamed.person', $user->username);
        $this->assertSame('renamed@partner.com', $user->email);
        // A company admin cannot move people to another company; only JMS can.
        $this->assertSame('Partner Company Inc.', $user->company);
        $this->assertSame('it_support', $user->role);
        $this->assertTrue(ActivityLog::where('user_id', $user->id)->where('action', 'profile_updated')->exists());
    }

    public function test_super_admin_can_edit_an_admin_but_admin_cannot(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = $this->u('admin@jmsoneit.com');
        $super = $this->u('superadmin@jmsoneit.com');
        $other = User::factory()->create(['role' => 'admin', 'username' => 'second.admin']);

        $this->actingAs($super)->patch("/users/{$admin->id}", $this->payload($admin, ['name' => 'Chief Admin']))->assertSessionHasNoErrors();
        $this->assertSame('Chief Admin', $admin->fresh()->name);

        $this->actingAs($admin)->patch("/users/{$other->id}", $this->payload($other, ['name' => 'Nope']))->assertForbidden();
        $this->actingAs($admin)->patch("/users/{$super->id}", $this->payload($super, ['name' => 'Nope']))->assertForbidden();
    }

    public function test_regular_people_cannot_edit_users(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = $this->u('user@jmsoneit.com');
        $this->actingAs($user)->patch("/users/{$user->id}", $this->payload($user, ['name' => 'Hacked']))->assertForbidden();
    }

    public function test_bad_input_reopens_the_edit_window_with_errors_and_saves_nothing(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = $this->u('admin@jmsoneit.com');
        $user  = $this->u('user@jmsoneit.com');

        $this->actingAs($admin)->from('/users')->patch("/users/{$user->id}", $this->payload($user, ['name' => '', 'username' => 'a b', 'email' => $admin->email]))
            ->assertSessionHasErrorsIn('edit', ['name', 'username', 'email'])->assertSessionHas('edit_user_id', $user->id);

        $this->assertNotSame('', $user->fresh()->name);

        // The page shows the window open again, with the problem explained.
        $this->actingAs($admin)->withSession(['edit_user_id' => $user->id])->get('/users')->assertOk()->assertSee('Edit user');
    }

    public function test_keeping_your_own_username_and_email_is_not_a_duplicate(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = $this->u('user@jmsoneit.com');
        $this->actingAs($this->u('admin@jmsoneit.com'))->patch("/users/{$user->id}", $this->payload($user))
            ->assertSessionHasNoErrors()->assertSessionHas('success');
    }

    public function test_admins_cannot_edit_or_promote_their_own_account(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = $this->u('admin@jmsoneit.com');

        $this->actingAs($admin)->patch("/users/{$admin->id}", $this->payload($admin, ['name' => 'Admin Renamed']))->assertForbidden();
        $this->assertSame('admin', $admin->fresh()->role);
        $this->assertNotSame('Admin Renamed', $admin->fresh()->name);
    }

    public function test_admin_cannot_promote_someone_to_admin(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = $this->u('user@jmsoneit.com');
        $this->actingAs($this->u('admin@jmsoneit.com'))->patch("/users/{$user->id}", $this->payload($user, ['role' => 'admin']))
            ->assertSessionHasErrorsIn('edit', 'role');
        $this->assertSame('user', $user->fresh()->role);
    }

    public function test_pages_show_the_edit_actions(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = $this->u('user@jmsoneit.com');
        foreach (['admin@jmsoneit.com', 'superadmin@jmsoneit.com'] as $email) {
            $x = $this->u($email);
            $this->actingAs($x)->get('/users')->assertOk()->assertSee('Manage users')->assertSee('edit-user', false)->assertSee('Save changes');
            $this->actingAs($x)->get("/users/{$user->id}")->assertOk()->assertSee('Edit user')->assertSee('Save changes');
        }
    }
}
