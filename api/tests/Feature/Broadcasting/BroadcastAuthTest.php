<?php

namespace Tests\Feature\Broadcasting;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class BroadcastAuthTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    public function test_user_can_authorize_own_tenant_channel_by_uuid(): void
    {
        $tenant = $this->createTenantWithRoles();
        $user = $this->createAdmin($tenant);

        $response = $this->actingAs($user)->postJson('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'private-tenant.'.$tenant->uuid,
        ]);

        $response->assertOk();
        $this->assertArrayHasKey('auth', $response->json());
    }

    public function test_user_cannot_authorize_another_tenant_channel(): void
    {
        $tenant = $this->createTenantWithRoles();
        $other = $this->createTenantWithRoles();
        $user = $this->createAdmin($tenant);

        $this->actingAs($user)->postJson('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'private-tenant.'.$other->uuid,
        ])->assertForbidden();
    }

    public function test_user_cannot_authorize_tenant_channel_by_numeric_id(): void
    {
        $tenant = $this->createTenantWithRoles();
        $user = $this->createAdmin($tenant);

        $this->actingAs($user)->postJson('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'private-tenant.'.$tenant->id,
        ])->assertForbidden();
    }
}
