<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JmsTest extends TestCase
{
    use RefreshDatabase;

    private function u(string $email): User { return User::where('email', $email)->firstOrFail(); }

    public function test_pages_and_permissions(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->assertSame(5, User::count());

        $this->get('/login')->assertOk()->assertSee('Sign in');
        $this->get('/register')->assertNotFound();
        $this->get('/login')->assertDontSee('Create an account')->assertDontSee('Sign up');

        $user = $this->u('user@jmsoneit.com');
        $support = $this->u('itsupport@jmsoneit.com');
        $admin = $this->u('admin@jmsoneit.com');
        $super = $this->u('superadmin@jmsoneit.com');

        // real login with default password
        $this->post('/login', ['login' => 'user@jmsoneit.com', 'password' => 'P@ssw0rd123'])->assertRedirect('/dashboard');
        $this->post('/logout');

        // Partners land on the submit-a-ticket form, engineers see their own work, admins triage.
        $this->actingAs($user)->get('/dashboard')->assertOk()
            ->assertSee('how can we help today?')->assertSee('Submit a new ticket')->assertSee('What kind of problem is it?')
            ->assertDontSee('Service desk overview');
        $this->actingAs($user)->get('/tickets/create')->assertRedirect('/dashboard');

        $this->actingAs($support)->get('/dashboard')->assertOk()->assertSee('here is your work');
        $this->actingAs($support)->get('/tickets/create')->assertForbidden();   // engineers do not log tickets
        foreach ([$admin, $super] as $x) {
            $this->actingAs($x)->get('/dashboard')->assertOk()->assertSee('Waiting for acceptance')->assertSee('Active work');
        }
        // A partner admin logs tickets for their company; the super admin is JMS itself and does not.
        $this->actingAs($admin)->get('/tickets/create')->assertOk()->assertSee('What kind of problem is it?');
        $this->actingAs($super)->get('/tickets/create')->assertForbidden();
        foreach ([$user, $support, $admin, $super] as $x) {
            $this->actingAs($x)->get('/tickets')->assertOk();
            $this->actingAs($x)->get('/tickets?status=open&search=Wi-Fi')->assertOk();
            $this->actingAs($x)->get('/profile')->assertOk();
        }

        // create ticket
        $this->actingAs($user)->post('/tickets', [
            'subject' => 'Test ticket', 'category' => 'Hardware', 'priority' => 'high', 'description' => 'The laptop is broken', 'when' => 'now',
        ])->assertRedirect();
        $t = Ticket::latest('id')->first();
        $this->assertStringStartsWith('JMS-', $t->ticket_no);

        $this->actingAs($user)->get("/tickets/{$t->id}")->assertOk()->assertSee('Test ticket');
        $this->actingAs($user)->get("/tickets/{$t->id}")->assertDontSee('Update progress')->assertDontSee('Accept & assign');

        // Engineers only see tickets an admin assigned to them; admins accept and assign.
        $this->actingAs($support)->get("/tickets/{$t->id}")->assertForbidden();
        $this->actingAs($admin)->get("/tickets/{$t->id}")->assertOk()->assertSee('Accept & assign');
        $this->actingAs($support)->post("/tickets/{$t->id}/assign", ['assigned_to' => $support->id, 'support_type' => 'remote', 'priority' => 'high'])->assertForbidden();
        $this->actingAs($admin)->post("/tickets/{$t->id}/assign", ['assigned_to' => $support->id, 'support_type' => 'remote', 'priority' => 'high'])->assertRedirect();
        $this->actingAs($support)->get("/tickets/{$t->id}")->assertOk()->assertSee('Update progress');

        // someone outside the ticket's company cannot see it: company privacy hides it completely (404)
        $other = User::factory()->create(['role' => 'user']);
        $this->actingAs($other)->get("/tickets/{$t->id}")->assertNotFound();

        // comments + internal note hidden from user
        $this->actingAs($support)->post("/tickets/{$t->id}/comments", ['body' => 'SECRETNOTE', 'is_internal' => 1])->assertRedirect();
        $this->actingAs($support)->post("/tickets/{$t->id}/comments", ['body' => 'PUBLICREPLY'])->assertRedirect();
        $this->actingAs($user)->get("/tickets/{$t->id}")->assertSee('PUBLICREPLY')->assertDontSee('SECRETNOTE');
        $this->actingAs($support)->get("/tickets/{$t->id}")->assertSee('SECRETNOTE');

        // update by staff only
        $this->actingAs($user)->patch("/tickets/{$t->id}", ['status' => 'closed', 'priority' => 'low'])->assertForbidden();
        $this->actingAs($support)->patch("/tickets/{$t->id}", ['status' => 'resolved', 'priority' => 'low', 'assigned_to' => $support->id])->assertForbidden();
        $this->actingAs($admin)->patch("/tickets/{$t->id}", ['status' => 'resolved', 'priority' => 'low', 'assigned_to' => $support->id])->assertRedirect();
        $this->assertNotNull($t->fresh()->resolved_at);

        // users admin
        $this->actingAs($user)->get('/users')->assertForbidden();
        $this->actingAs($support)->get('/users')->assertForbidden();
        $this->actingAs($admin)->get('/users')->assertOk();
        $this->actingAs($super)->get('/users')->assertOk();

        // admin cannot create admin; super admin can
        $this->actingAs($admin)->post('/users', ['name' => 'Xavier', 'username' => 'xadmin', 'email' => 'x@jmsoneit.com', 'role' => 'admin'])->assertSessionHasErrors('role');
        $this->actingAs($admin)->post('/users', ['name' => 'Eng', 'username' => 'Eng.One', 'company' => 'JMS', 'email' => 'Eng@JMSoneit.com', 'role' => 'it_support'])->assertSessionHasNoErrors();
        $eng = $this->u('eng@jmsoneit.com');
        $this->assertSame('it_support', $eng->role);
        // A partner admin's new engineer stays in the admin's own company, whatever company text is sent.
        $this->assertSame($admin->company_id, $eng->company_id);
        $this->assertSame($admin->company, $eng->company);
        $this->assertTrue(\Hash::check('P@ssw0rd123', $eng->password));
        $eng->update(['password' => \Hash::make('changed')]);
        $this->actingAs($admin)->post("/users/{$eng->id}/reset-password")->assertRedirect();
        $this->assertTrue(\Hash::check('P@ssw0rd123', $eng->fresh()->password));
        $this->actingAs($admin)->post("/users/{$super->id}/reset-password")->assertForbidden();
        $this->actingAs($user)->post("/users/{$eng->id}/reset-password")->assertForbidden();
        // A super admin must place the new admin: in a company, or in JMS's own team ("jms").
        $this->actingAs($super)->post('/users', ['name' => 'Xavier', 'username' => 'xadmin', 'email' => 'x@jmsoneit.com', 'role' => 'admin', 'company_id' => 'jms'])->assertSessionHasNoErrors();
        $this->assertTrue(\Hash::check('P@ssw0rd123', $this->u('x@jmsoneit.com')->password));
        // admin cannot demote/delete super admin
        $this->actingAs($admin)->patch("/users/{$super->id}", ['role' => 'user'])->assertForbidden();
        $this->actingAs($admin)->delete("/users/{$super->id}")->assertForbidden();

        // brand new account (created with mixed-case input) signs in with email OR username
        auth()->logout();
        $this->post('/login', ['login' => 'eng.one', 'password' => 'P@ssw0rd123'])->assertRedirect('/dashboard');
        auth()->logout();
        $this->post('/login', ['login' => 'ENG.ONE', 'password' => 'P@ssw0rd123'])->assertRedirect('/dashboard');
        $this->assertSame('eng.one', $this->u('eng@jmsoneit.com')->username);
        auth()->logout();
        $this->post('/login', ['login' => 'eng@jmsoneit.com', 'password' => 'P@ssw0rd123'])->assertRedirect('/dashboard');
        auth()->logout();

        // no social sign-in, no registration
        $this->get('/auth/google/redirect')->assertNotFound();
        $this->get('/auth/facebook/redirect')->assertNotFound();
        $this->get('/login')->assertDontSee('Google')->assertDontSee('Facebook')->assertSee('Email or username');
    }

    public function test_login_validation_and_errors(): void
    {
        $this->seed(DatabaseSeeder::class);

        // required fields
        $this->post('/login', ['login' => '', 'password' => ''])
            ->assertSessionHasErrors(['login' => 'Enter your email address or username.', 'password' => 'Enter your password.']);

        // wrong password / unknown account -> one clear generic message (no account enumeration)
        $this->post('/login', ['login' => 'user', 'password' => 'wrong'])
            ->assertSessionHasErrors('credentials');
        $this->post('/login', ['login' => 'nobody@nowhere.com', 'password' => 'wrong'])
            ->assertSessionHasErrors('credentials');
        $this->assertGuest();

        // username login (case-insensitive) works
        $this->post('/login', ['login' => 'AdMiN', 'password' => 'P@ssw0rd123'])->assertRedirect('/dashboard');
        $this->assertAuthenticated();
    }

    public function test_login_is_rate_limited(): void
    {
        $this->seed(DatabaseSeeder::class);
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['login' => 'user', 'password' => 'bad']);
        }
        $this->post('/login', ['login' => 'user', 'password' => 'P@ssw0rd123'])
            ->assertSessionHasErrors('credentials');
        $this->assertGuest();
    }

    public function test_admin_form_validation(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = $this->u('admin@jmsoneit.com');
        $user = $this->u('user@jmsoneit.com');

        $this->actingAs($admin)->post('/users', ['name' => '', 'username' => 'a b', 'email' => 'not-an-email', 'role' => 'user'])
            ->assertSessionHasErrors(['name', 'username', 'email']);
        $this->actingAs($admin)->post('/users', ['name' => 'Dup', 'username' => 'admin', 'email' => 'dup@x.com', 'role' => 'user'])
            ->assertSessionHasErrors(['username' => 'That username is already taken.']);
        $this->actingAs($admin)->post('/users', ['name' => 'Dup', 'username' => 'dup', 'email' => 'user@jmsoneit.com', 'role' => 'user'])
            ->assertSessionHasErrors(['email' => 'An account with this email already exists.']);
        $this->actingAs($user)->post('/tickets', ['subject' => 'Printer offline', 'category' => 'Printer / Peripherals', 'priority' => 'low', 'description' => 'Printer shows offline', 'contact_phone' => 'call me'])->assertSessionHasErrors('contact_phone');
        $this->actingAs($user)->post('/tickets', ['subject' => 'Printer offline', 'category' => 'Printer / Peripherals', 'priority' => 'low', 'description' => 'Printer shows offline', 'when' => 'now', 'contact_phone' => '+63 900 000 0000', 'location' => 'Cebu branch'])->assertSessionHasNoErrors();
        $this->assertSame('Cebu branch', Ticket::where('subject', 'Printer offline')->first()->location);
        $this->actingAs($admin)->post('/tickets', ['subject' => 'abc', 'category' => '', 'priority' => 'high', 'description' => 'short'])
            ->assertSessionHasErrors(['subject', 'category', 'description']);
    }
}
