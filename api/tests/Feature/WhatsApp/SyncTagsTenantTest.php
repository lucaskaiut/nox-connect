<?php

namespace Tests\Feature\WhatsApp;

use App\Modules\Tenant\Support\Facades\TenantContext;
use App\Modules\WhatsApp\Models\WhatsAppContact;
use App\Modules\WhatsApp\Models\WhatsAppConversation;
use App\Modules\WhatsApp\Models\WhatsAppTag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class SyncTagsTenantTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    public function test_sync_tags_rejects_foreign_tag_ids(): void
    {
        [$umbrella, $child] = $this->createOperationalChild();
        $admin = $this->createAdmin($child);

        $foreignTenant = $this->createTenantWithRoles(['domain' => 'foreign.com']);
        $foreignTag = WhatsAppTag::query()->withoutTenancy()->create([
            'tenant_id' => $foreignTenant->getKey(),
            'name' => 'Foreign',
            'color' => '#000000',
            'sort_order' => 0,
        ]);

        Sanctum::actingAs($admin);
        TenantContext::set($child);

        $conversation = $this->createConversation($child);
        $ownTag = WhatsAppTag::query()->create([
            'name' => 'VIP',
            'color' => '#10B981',
            'sort_order' => 0,
        ]);

        $this->postJson("/api/whatsapp/conversations/{$conversation->id}/tags", [
            'tag_ids' => [$ownTag->id, $foreignTag->id],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['tag_ids.1']);

        $this->assertDatabaseMissing('whatsapp_conversation_tags', [
            'conversation_id' => $conversation->id,
            'tag_id' => $foreignTag->id,
        ]);
    }

    public function test_sync_tags_succeeds_with_same_tenant_tags(): void
    {
        [$umbrella, $child] = $this->createOperationalChild();
        $admin = $this->createAdmin($child);

        Sanctum::actingAs($admin);
        TenantContext::set($child);

        $conversation = $this->createConversation($child);
        $tag = WhatsAppTag::query()->create([
            'name' => 'VIP',
            'color' => '#10B981',
            'sort_order' => 0,
        ]);

        $this->postJson("/api/whatsapp/conversations/{$conversation->id}/tags", [
            'tag_ids' => [$tag->id],
        ])->assertOk();

        $this->assertDatabaseHas('whatsapp_conversation_tags', [
            'conversation_id' => $conversation->id,
            'tag_id' => $tag->id,
        ]);
    }

    private function createConversation($tenant): WhatsAppConversation
    {
        $contact = WhatsAppContact::query()->create([
            'external_contact_id' => '5511999990002',
            'profile_name' => 'Cliente Teste',
            'display_name' => 'Cliente Teste',
        ]);

        return WhatsAppConversation::query()->create([
            'contact_id' => $contact->id,
            'status' => 'open',
        ]);
    }
}
