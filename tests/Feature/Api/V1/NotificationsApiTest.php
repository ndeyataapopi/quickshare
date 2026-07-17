<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Modules\Notifications\Notifications\WelcomeNotification;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationsApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->user = User::factory()->active()->create();
        $this->assignClientRole($this->user);
    }

    // ─── List Notifications ───────────────────────────────────────────

    public function test_user_can_list_notifications(): void
    {
        // Send a notification to user
        $this->user->notify(new WelcomeNotification([], 'database'));

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/notifications');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data',
                'meta' => ['current_page', 'per_page', 'total'],
            ]);
    }

    public function test_unauthenticated_cannot_list_notifications(): void
    {
        $this->getJson('/api/v1/notifications')
            ->assertStatus(401);
    }

    // ─── Count ────────────────────────────────────────────────────────

    public function test_can_get_notification_count(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/notifications/count');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['total', 'unread'],
            ])
            ->assertJsonPath('data.total', 0)
            ->assertJsonPath('data.unread', 0);
    }

    // ─── Unread ───────────────────────────────────────────────────────

    public function test_can_list_unread_notifications(): void
    {
        $this->user->notify(new WelcomeNotification([], 'database'));

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/notifications/unread');

        $response->assertOk();
        $this->assertEquals(1, $response->json('meta.total'));
    }

    // ─── Mark as Read ─────────────────────────────────────────────────

    public function test_can_mark_notification_as_read(): void
    {
        $this->user->notify(new WelcomeNotification([], 'database'));
        $notification = $this->user->notifications()->first();

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/notifications/{$notification->id}/read");

        $response->assertOk()
            ->assertJsonPath('data.is_read', true);
    }

    public function test_mark_all_as_read(): void
    {
        $this->user->notify(new WelcomeNotification([], 'database'));
        $this->user->notify(new WelcomeNotification([], 'database'));

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/notifications/read-all');

        $response->assertOk()
            ->assertJsonPath('data.marked_read', 2);

        $this->assertEquals(0, $this->user->unreadNotifications()->count());
    }

    // ─── Delete ───────────────────────────────────────────────────────

    public function test_can_delete_a_notification(): void
    {
        $this->user->notify(new WelcomeNotification([], 'database'));
        $notification = $this->user->notifications()->first();

        $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/v1/notifications/{$notification->id}")
            ->assertOk();

        $this->assertDatabaseMissing('notifications', ['id' => $notification->id]);
    }

    public function test_can_delete_all_notifications(): void
    {
        $this->user->notify(new WelcomeNotification([], 'database'));
        $this->user->notify(new WelcomeNotification([], 'database'));
        $this->user->notify(new WelcomeNotification([], 'database'));

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson('/api/v1/notifications');

        $response->assertOk()
            ->assertJsonPath('data.deleted', 3);

        $this->assertEquals(0, $this->user->notifications()->count());
    }
}
