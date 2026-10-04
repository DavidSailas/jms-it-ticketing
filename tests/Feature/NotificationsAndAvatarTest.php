<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NotificationsAndAvatarTest extends TestCase
{
    use RefreshDatabase;

    private function u(string $username): User { return User::where('username', $username)->firstOrFail(); }

    private function notifCount(User $u): int { return $u->fresh()->notifications()->count(); }

    public function test_notifications_follow_the_ticket_workflow(): void
    {
        $this->seed(DatabaseSeeder::class);
        [$user, $support, $admin, $super] = [$this->u('user'), $this->u('itsupport'), $this->u('admin'), $this->u('superadmin')];

        // 1. Partner submits a CRITICAL ticket -> admins are told (they triage it); engineers and the partner are not.
        $this->actingAs($user)->post('/tickets', [
            'subject' => 'Server room on fire', 'category' => 'Hardware', 'priority' => 'critical', 'description' => 'Smoke and alarms everywhere', 'when' => 'now',
        ])->assertRedirect();
        $ticket = Ticket::latest('id')->first();

        foreach ([$admin, $super] as $staff) {
            $this->assertSame(1, $this->notifCount($staff), $staff->username);
            $n = $staff->notifications()->first();
            $this->assertSame('urgent', $n->data['kind']);
            $this->assertSame("/tickets/{$ticket->id}", $n->data['url']);
            $this->assertSame($ticket->ticket_no, $n->data['ticket_no']);
        }
        $this->assertSame(0, $this->notifCount($support));   // engineers hear about it only once it is assigned to them
        $this->assertSame(0, $this->notifCount($user));

        // 2. Admin assigns it to IT Support + moves it to In progress.
        $this->actingAs($admin)->patch("/tickets/{$ticket->id}", ['status' => 'in_progress', 'priority' => 'critical', 'assigned_to' => $support->id])->assertRedirect();
        $this->assertSame(1, $this->notifCount($support));                                   // assigned to you
        $this->assertContains('assigned', $support->fresh()->notifications->pluck('data.kind')->all());
        $this->assertSame(2, $this->notifCount($user));                                      // engineer assigned + status changed
        $this->assertSame(1, $this->notifCount($admin));                                     // actor not notified of own action

        // 3. Engineer replies publicly -> partner is notified; internal note -> partner is NOT.
        $before = $this->notifCount($user);
        $this->actingAs($support)->post("/tickets/{$ticket->id}/comments", ['body' => 'We are on our way.'])->assertRedirect();
        $this->assertSame($before + 1, $this->notifCount($user));
        $this->assertContains('reply', $user->fresh()->notifications->pluck('data.kind')->all());

        $this->actingAs($support)->post("/tickets/{$ticket->id}/comments", ['body' => 'SECRET staff note', 'is_internal' => 1]);
        $this->assertSame($before + 1, $this->notifCount($user));

        // 4. Partner replies -> assigned engineer is notified.
        $beforeSupport = $this->notifCount($support);
        $this->actingAs($user)->post("/tickets/{$ticket->id}/comments", ['body' => 'Thank you!']);
        $this->assertSame($beforeSupport + 1, $this->notifCount($support));

        // 5. Resolved -> partner gets a "resolved" notification.
        $this->actingAs($admin)->patch("/tickets/{$ticket->id}", ['status' => 'resolved', 'priority' => 'critical', 'assigned_to' => $support->id]);
        $this->assertContains('resolved', $user->fresh()->notifications->pluck('data.kind')->all());
    }

    public function test_password_reset_notifies_the_user(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = $this->u('user');
        $this->actingAs($this->u('admin'))->post("/users/{$user->id}/reset-password")->assertRedirect();

        $n = $user->fresh()->notifications()->first();
        $this->assertSame('security', $n->data['kind']);
        $this->assertSame('/profile', $n->data['url']);
    }

    public function test_bell_feed_open_and_mark_read(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = $this->u('user');
        $support = $this->u('admin');   // admins are the ones alerted about new tickets

        $this->actingAs($user)->post('/tickets', ['subject' => 'Wi-Fi is down', 'category' => 'Network / Internet', 'priority' => 'high', 'description' => 'No internet at all', 'when' => 'now']);
        $ticket = Ticket::latest('id')->first();

        // bell is rendered on the page with the unread count
        $this->actingAs($support)->get('/dashboard')->assertOk()->assertSee('notificationBell', false)->assertSee('View all notifications');

        $feed = $this->actingAs($support)->getJson('/notifications/feed')->assertOk()->assertJsonPath('unread', 1)->json();
        $this->assertSame('New ticket ' . $ticket->ticket_no, $feed['items'][0]['title']);
        $this->assertFalse($feed['items'][0]['read']);

        // someone else cannot open it
        $id = $feed['items'][0]['id'];
        $this->actingAs($user)->get("/notifications/{$id}/open")->assertNotFound();
        $this->assertSame(1, $support->fresh()->unreadNotifications()->count());

        // owner opens it -> goes to the ticket, marked as read
        $this->actingAs($support)->get("/notifications/{$id}/open")->assertRedirect("/tickets/{$ticket->id}");
        $this->assertSame(0, $support->fresh()->unreadNotifications()->count());

        // mark all as read + full page
        $this->actingAs($user)->post('/tickets', ['subject' => 'Another one', 'category' => 'Other', 'priority' => 'low', 'description' => 'Some details here', 'when' => 'now']);
        $this->actingAs($support)->get('/notifications')->assertOk()->assertSee('Another one');
        $this->actingAs($support)->get('/notifications?filter=unread')->assertOk();
        $this->actingAs($support)->postJson('/notifications/read-all')->assertOk()->assertJson(['ok' => true]);
        $this->assertSame(0, $support->fresh()->unreadNotifications()->count());

        // guests are sent to login
        auth()->logout();
        $this->get('/notifications/feed')->assertRedirect('/login');
    }

    public function test_profile_photo_upload_show_and_remove(): void
    {
        Storage::fake('local');
        $this->seed(DatabaseSeeder::class);
        $user = $this->u('user');
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');

        $this->actingAs($user)->get('/profile')->assertOk()->assertSee('Profile photo')->assertDontSee('Remove photo');

        // wrong type / too big are rejected with friendly messages
        $this->actingAs($user)->post('/profile/avatar', ['avatar' => UploadedFile::fake()->createWithContent('doc.txt', 'hello')])
            ->assertSessionHasErrors(['avatar' => 'The photo must be a JPG, PNG or WebP image.']);
        $this->actingAs($user)->post('/profile/avatar', ['avatar' => UploadedFile::fake()->create('big.png', 3000, 'image/png')])
            ->assertSessionHasErrors('avatar');
        $this->assertNull($user->fresh()->avatar);

        // valid upload
        $this->actingAs($user)->post('/profile/avatar', ['avatar' => UploadedFile::fake()->createWithContent('me.png', $png)])
            ->assertSessionHasNoErrors()->assertSessionHas('success');
        $path = $user->fresh()->avatar;
        $this->assertNotNull($path);
        Storage::disk('local')->assertExists($path);

        // shown on pages, and streamed to signed-in users only
        $this->actingAs($user)->get('/profile')->assertSee('/avatars/' . $user->id, false)->assertSee('Remove photo');
        $this->actingAs($user)->get("/avatars/{$user->id}")->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->actingAs($this->u('admin'))->get("/avatars/{$user->id}")->assertOk();
        $this->actingAs($this->u('admin'))->get('/users')->assertSee('/avatars/' . $user->id, false);

        // replacing deletes the old file
        $this->actingAs($user)->post('/profile/avatar', ['avatar' => UploadedFile::fake()->createWithContent('new.png', $png)]);
        Storage::disk('local')->assertMissing($path);
        $newPath = $user->fresh()->avatar;
        Storage::disk('local')->assertExists($newPath);

        // remove
        $this->actingAs($user)->delete('/profile/avatar')->assertSessionHas('success');
        $this->assertNull($user->fresh()->avatar);
        Storage::disk('local')->assertMissing($newPath);
        $this->actingAs($user)->get("/avatars/{$user->id}")->assertNotFound();

        auth()->logout();
        $this->get("/avatars/{$user->id}")->assertRedirect('/login');
    }
}
