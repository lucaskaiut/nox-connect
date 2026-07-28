<?php

namespace Tests\Feature\WhatsApp;

use App\Modules\Tenant\Support\Facades\TenantContext;
use App\Modules\User\Models\User;
use App\Modules\WhatsApp\Models\KanbanStage;
use App\Modules\WhatsApp\Models\WhatsAppContact;
use App\Modules\WhatsApp\Models\WhatsAppConversation;
use App\Modules\WhatsApp\Events\ConversationAssigned;
use App\Modules\WhatsApp\Events\KanbanCardMoved;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class ConversationAssignStageTenantTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake([
            ConversationAssigned::class,
            KanbanCardMoved::class,
        ]);
    }

    public function test_assign_rejects_user_from_other_tenant(): void
    {
        [$umbrella, $child] = $this->createOperationalChild();
        $admin = $this->createAdmin($child);

        $foreignTenant = $this->createTenantWithRoles(['domain' => 'foreign.com']);
        $foreignUser = User::factory()->for($foreignTenant)->create();

        Sanctum::actingAs($admin);
        TenantContext::set($child);

        $conversation = $this->createConversation($child);

        $this->postJson("/api/whatsapp/conversations/{$conversation->id}/assign", [
            'user_id' => $foreignUser->uuid,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['user_id']);
    }

    public function test_assign_succeeds_for_user_in_same_tenant(): void
    {
        [$umbrella, $child] = $this->createOperationalChild();
        $admin = $this->createAdmin($child);
        $agent = User::factory()->for($child)->create();

        Sanctum::actingAs($admin);
        TenantContext::set($child);

        $conversation = $this->createConversation($child);

        $this->postJson("/api/whatsapp/conversations/{$conversation->id}/assign", [
            'user_id' => $agent->uuid,
        ])->assertOk();

        $this->assertDatabaseHas('whatsapp_conversation_assignments', [
            'conversation_id' => $conversation->id,
            'user_id' => $agent->uuid,
        ]);
    }

    public function test_move_stage_rejects_stage_from_other_tenant(): void
    {
        [$umbrella, $child] = $this->createOperationalChild();
        $admin = $this->createAdmin($child);

        $foreignTenant = $this->createTenantWithRoles(['domain' => 'foreign.com']);
        $foreignStage = KanbanStage::query()->withoutTenancy()->create([
            'tenant_id' => $foreignTenant->getKey(),
            'name' => 'Foreign',
            'color' => '#000000',
            'sort_order' => 0,
        ]);

        Sanctum::actingAs($admin);
        TenantContext::set($child);

        $conversation = $this->createConversation($child);

        $this->postJson("/api/whatsapp/kanban/conversations/{$conversation->id}/move", [
            'stage_id' => $foreignStage->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['stage_id']);
    }

    public function test_move_stage_succeeds_for_stage_in_same_tenant(): void
    {
        [$umbrella, $child] = $this->createOperationalChild();
        $admin = $this->createAdmin($child);

        Sanctum::actingAs($admin);
        TenantContext::set($child);

        $stage = KanbanStage::query()->create([
            'name' => 'Novo Lead',
            'color' => '#6B7280',
            'sort_order' => 0,
        ]);

        $conversation = $this->createConversation($child);

        $this->postJson("/api/whatsapp/kanban/conversations/{$conversation->id}/move", [
            'stage_id' => $stage->id,
        ])->assertOk();

        $this->assertSame($stage->id, $conversation->fresh()->current_stage_id);
    }

    private function createConversation($tenant): WhatsAppConversation
    {
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
