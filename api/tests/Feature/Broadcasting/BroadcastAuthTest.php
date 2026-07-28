<?php

namespace Tests\Feature\Broadcasting;

use App\Modules\Tenant\Resolution\Strategies\AuthenticatedUserStrategy;
use App\Modules\Tenant\Support\Facades\TenantContext;
use App\Modules\WhatsApp\Models\WhatsAppContact;
use App\Modules\WhatsApp\Models\WhatsAppConversation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
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

    public function test_master_can_authorize_child_tenant_channel(): void
    {
        $umbrella = $this->createTenantWithRoles();
        $child = $this->createChildTenant($umbrella);
        $master = $this->createMaster($umbrella);

        Sanctum::actingAs($master);

        $this->postJson('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'private-tenant.'.$child->uuid,
        ], [
            AuthenticatedUserStrategy::HEADER => $child->uuid,
        ])->assertOk();
    }

    public function test_master_cannot_authorize_tenant_from_another_group(): void
    {
        $umbrellaA = $this->createTenantWithRoles();
        $umbrellaB = $this->createTenantWithRoles(['domain' => 'grupo-b.com']);
        $foreignChild = $this->createChildTenant($umbrellaB);
        $master = $this->createMaster($umbrellaA);

        Sanctum::actingAs($master);

        $this->postJson('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'private-tenant.'.$foreignChild->uuid,
        ])->assertForbidden();
    }

    public function test_user_can_authorize_conversation_in_own_tenant(): void
    {
        [$umbrella, $child] = $this->createOperationalChild();
        $admin = $this->createAdmin($child);
        $conversation = $this->createConversation($child);

        Sanctum::actingAs($admin);

        $this->postJson('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'private-conversation.'.$conversation->id,
        ])->assertOk();
    }

    public function test_user_cannot_authorize_conversation_from_other_tenant(): void
    {
        [$umbrellaA, $childA] = $this->createOperationalChild();
        [$umbrellaB, $childB] = $this->createOperationalChild();
        $admin = $this->createAdmin($childA);
        $foreignConversation = $this->createConversation($childB);

        Sanctum::actingAs($admin);

        $this->postJson('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'private-conversation.'.$foreignConversation->id,
        ])->assertForbidden();
    }

    private function createConversation($tenant): WhatsAppConversation
    {
        TenantContext::set($tenant);

        $contact = WhatsAppContact::query()->create([
            'external_contact_id' => '5511999990001',
            'profile_name' => 'Cliente Teste',
            'display_name' => 'Cliente Teste',
        ]);

        return WhatsAppConversation::query()->create([
            'contact_id' => $contact->id,
            'status' => 'open',
        ]);
    }
}
