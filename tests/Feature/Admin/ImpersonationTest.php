<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Modules\KYC\Models\KycSubmission;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImpersonationTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $admin;
    protected User $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->superAdmin = User::factory()->active()->create();
        $this->superAdmin->assignRole('admin');
        $this->superAdmin->givePermissionTo('impersonate_users');

        $this->admin = User::factory()->active()->create();
        $this->admin->assignRole('admin');

        $this->client = User::factory()->active()->create();
        $this->client->assignRole('client');
        KycSubmission::factory()->approved()->create(['user_id' => $this->client->id]);
    }

    public function test_super_admin_can_impersonate_client(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->post(route('admin.impersonate.start', $this->client));

        $response->assertRedirect(route('client.dashboard'));
        $this->assertAuthenticatedAs($this->client);
        $this->assertEquals($this->superAdmin->id, session('impersonate.original_id'));
    }

    public function test_normal_admin_cannot_impersonate(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('admin.impersonate.start', $this->client));

        $response->assertForbidden();
        $this->assertAuthenticatedAs($this->admin);
    }

    public function test_session_is_preserved_during_impersonation(): void
    {
        $this->actingAs($this->superAdmin)
            ->post(route('admin.impersonate.start', $this->client))
            ->assertRedirect(route('client.dashboard'));

        $this->assertEquals($this->superAdmin->id, session('impersonate.original_id'));
        $this->assertEquals($this->client->id, session('impersonate.client_id'));
    }

    public function test_return_to_admin_restores_original_session(): void
    {
        $this->actingAs($this->superAdmin)
            ->post(route('admin.impersonate.start', $this->client))
            ->assertRedirect(route('client.dashboard'));

        $response = $this->post(route('admin.impersonate.stop'));

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($this->superAdmin);
        $this->assertFalse(session()->has('impersonate.original_id'));
        $this->assertFalse(session()->has('impersonate.client_id'));
    }

    public function test_audit_log_is_created_for_impersonation(): void
    {
        $this->actingAs($this->superAdmin)
            ->post(route('admin.impersonate.start', $this->client))
            ->assertRedirect(route('client.dashboard'));

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $this->superAdmin->id,
            'action' => 'impersonation.started',
            'subject_type' => User::class,
            'subject_id' => $this->client->id,
        ]);

        $this->post(route('admin.impersonate.stop'))
            ->assertRedirect(route('admin.dashboard'));

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $this->superAdmin->id,
            'action' => 'impersonation.ended',
        ]);
    }

    public function test_nested_impersonation_is_prevented(): void
    {
        $this->actingAs($this->superAdmin)
            ->post(route('admin.impersonate.start', $this->client))
            ->assertRedirect(route('client.dashboard'));

        $anotherClient = User::factory()->active()->create();
        $anotherClient->assignRole('client');

        // Current user is now a client; the admin route middleware blocks access.
        $this->post(route('admin.impersonate.start', $anotherClient))
            ->assertForbidden();
    }

    public function test_unauthorized_users_receive_403(): void
    {
        $client2 = User::factory()->active()->create();
        $client2->assignRole('client');

        $this->actingAs($client2)
            ->post(route('admin.impersonate.start', $this->client))
            ->assertForbidden();
    }

    public function test_sensitive_profile_update_is_blocked_while_impersonating(): void
    {
        $this->actingAs($this->superAdmin)
            ->post(route('admin.impersonate.start', $this->client))
            ->assertRedirect(route('client.dashboard'));

        $this->patch(route('client.profile.update'), [
            'first_name' => 'Hacked',
        ])->assertForbidden();
    }
}
