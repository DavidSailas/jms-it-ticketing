<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\TicketActivity;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The bell shows the latest 10; everything else is on the Notifications page. */
class NotificationBellLimitTest extends TestCase
{
    use RefreshDatabase;

    private function engineerWith(int $count): User
    {
        $this->seed(DatabaseSeeder::class);
        $me = User::where('username', 'itsupport')->firstOrFail();

        foreach (range(1, $count) as $i) {
            $me->notify(new TicketActivity(['kind' => 'comment', 'title' => "Update {$i}", 'message' => "Message number {$i}", 'url' => '/dashboard']));
            // Space them out so "latest" is unambiguous.
            $me->notifications()->latest()->first()?->forceFill(['created_at' => now()->subMinutes(100 - $i)])->save();
        }

        return $me->fresh();
    }

    public function test_the_bell_shows_ten_and_says_how_many_more_there_are(): void
    {
        $me = $this->engineerWith(14);
        $feed = $me->notificationFeed();

        $this->assertCount(10, $feed['items']);
        $this->assertSame(14, $feed['total']);
        $this->assertSame(4, $feed['more']);
        $this->assertSame(14, $feed['unread']);                       // the badge counts all of them
        $this->assertSame('Update 14', $feed['items'][0]['title']);   // newest first
        $this->assertSame('Update 5', $feed['items'][9]['title']);    // 10th newest; 1 to 4 are only on the full page
    }

    public function test_the_live_feed_follows_the_same_rule(): void
    {
        $me = $this->engineerWith(12);

        $json = $this->actingAs($me)->getJson('/notifications/feed')->assertOk()->json();
        $this->assertCount(10, $json['items']);
        $this->assertSame(2, $json['more']);
    }

    public function test_nothing_hidden_when_there_are_ten_or_fewer(): void
    {
        $me = $this->engineerWith(10);

        $this->assertCount(10, $me->notificationFeed()['items']);
        $this->assertSame(0, $me->notificationFeed()['more']);
    }

    public function test_the_full_page_lists_the_rest_grouped_by_day(): void
    {
        $me = $this->engineerWith(14);

        $this->actingAs($me)->get('/notifications')
            ->assertOk()
            ->assertSee('Showing')->assertSee('of')->assertSee('14')
            ->assertSee('Today')
            ->assertSee('Update 1')            // the oldest one the bell hides
            ->assertSee('Update 14');
    }

    public function test_the_bell_menu_offers_the_full_page_with_a_count(): void
    {
        $me = $this->engineerWith(3);

        $this->actingAs($me)->get('/dashboard')->assertOk()
            ->assertSee('View all notifications')
            ->assertSee(route('notifications.index'), false);
    }
}
