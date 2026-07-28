<?php

namespace Tests\Feature\WhatsApp;

use App\Modules\Tenant\Models\Tenant;
use App\Modules\WhatsApp\DTOs\IncomingMessageDTO;
use App\Modules\WhatsApp\DTOs\MessageStatusUpdateDTO;
use App\Modules\WhatsApp\DTOs\WebhookResultDTO;
use App\Modules\WhatsApp\Enums\MessageDirection;
use App\Modules\WhatsApp\Enums\MessageStatus;
use App\Modules\WhatsApp\Events\MessageDelivered;
use App\Modules\WhatsApp\Events\MessageRead;
use App\Modules\WhatsApp\Events\MessageReceived;
use App\Modules\WhatsApp\Models\WhatsAppContact;
use App\Modules\WhatsApp\Models\WhatsAppConversation;
use App\Modules\WhatsApp\Models\WhatsAppMessage;
use App\Modules\WhatsApp\Services\WhatsAppWebhookService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class WhatsAppMessageTenantIsolationTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake([
            MessageReceived::class,
            MessageDelivered::class,
            MessageRead::class,
        ]);
    }

    public function test_status_update_from_other_tenant_does_not_alter_foreign_message(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $externalMessageId = 'wamid.shared-across-tenants';

        $messageB = $this->seedOutboundMessage($tenantB, $externalMessageId, MessageStatus::Sent);

        app(WhatsAppWebhookService::class)->handleNormalized(
            $tenantA,
            new WebhookResultDTO(
                statuses: [
                    new MessageStatusUpdateDTO(
                        externalMessageId: $externalMessageId,
                        status: 'delivered',
                    ),
                ],
            ),
        );

        $messageB->refresh();

        $this->assertSame(MessageStatus::Sent->value, $messageB->status);
        $this->assertNull($messageB->delivered_at);
        $this->assertSame(1, WhatsAppMessage::query()->withoutTenancy()->where('external_message_id', $externalMessageId)->count());
        Event::assertNotDispatched(MessageDelivered::class);
    }

    public function test_upsert_same_tenant_is_idempotent(): void
    {
        $tenant = Tenant::factory()->create();
        $externalMessageId = 'wamid.idempotent-inbound';
        $service = app(WhatsAppWebhookService::class);

        $payload = new WebhookResultDTO(
            messages: [
                new IncomingMessageDTO(
                    externalMessageId: $externalMessageId,
                    externalContactId: '5511999999999',
                    messageType: 'text',
                    content: 'Olá',
                    profileName: 'Cliente',
                ),
            ],
        );

        $service->handleNormalized($tenant, $payload);
        $service->handleNormalized($tenant, $payload);

        $messages = WhatsAppMessage::query()
            ->withoutTenancy()
            ->where('tenant_id', $tenant->id)
            ->where('external_message_id', $externalMessageId)
            ->get();

        $this->assertCount(1, $messages);
        $this->assertSame('Olá', $messages->first()->content);
        $this->assertSame(MessageDirection::Inbound->value, $messages->first()->direction);
    }

    public function test_same_external_message_id_can_exist_in_different_tenants(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $externalMessageId = 'wamid.collision-allowed-per-tenant';
        $service = app(WhatsAppWebhookService::class);

        $service->handleNormalized($tenantA, new WebhookResultDTO(
            messages: [
                new IncomingMessageDTO(
                    externalMessageId: $externalMessageId,
                    externalContactId: '5511888888888',
                    messageType: 'text',
                    content: 'Mensagem A',
                ),
            ],
        ));

        $service->handleNormalized($tenantB, new WebhookResultDTO(
            messages: [
                new IncomingMessageDTO(
                    externalMessageId: $externalMessageId,
                    externalContactId: '5511777777777',
                    messageType: 'text',
                    content: 'Mensagem B',
                ),
            ],
        ));

        $this->assertSame(2, WhatsAppMessage::query()->withoutTenancy()->where('external_message_id', $externalMessageId)->count());
        $this->assertSame(
            'Mensagem A',
            WhatsAppMessage::query()->forTenant($tenantA)->where('external_message_id', $externalMessageId)->value('content'),
        );
        $this->assertSame(
            'Mensagem B',
            WhatsAppMessage::query()->forTenant($tenantB)->where('external_message_id', $externalMessageId)->value('content'),
        );
    }

    public function test_status_update_only_affects_message_of_same_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $externalMessageId = 'wamid.status-scoped';

        $messageA = $this->seedOutboundMessage($tenantA, $externalMessageId, MessageStatus::Sent);
        $messageB = $this->seedOutboundMessage($tenantB, $externalMessageId, MessageStatus::Sent);

        app(WhatsAppWebhookService::class)->handleNormalized(
            $tenantA,
            new WebhookResultDTO(
                statuses: [
                    new MessageStatusUpdateDTO(
                        externalMessageId: $externalMessageId,
                        status: 'read',
                    ),
                ],
            ),
        );

        $messageA->refresh();
        $messageB->refresh();

        $this->assertSame(MessageStatus::Read->value, $messageA->status);
        $this->assertNotNull($messageA->read_at);
        $this->assertSame(MessageStatus::Sent->value, $messageB->status);
        $this->assertNull($messageB->read_at);
    }

    private function seedOutboundMessage(Tenant $tenant, string $externalMessageId, MessageStatus $status): WhatsAppMessage
    {
        $contact = WhatsAppContact::query()->withoutTenancy()->create([
            'tenant_id' => $tenant->id,
            'external_contact_id' => '5511'.str_pad((string) $tenant->id, 8, '0', STR_PAD_LEFT),
            'profile_name' => 'Contato '.$tenant->id,
            'display_name' => 'Contato '.$tenant->id,
        ]);

        $conversation = WhatsAppConversation::query()->withoutTenancy()->create([
            'tenant_id' => $tenant->id,
            'contact_id' => $contact->id,
            'status' => 'open',
        ]);

        return WhatsAppMessage::query()->withoutTenancy()->create([
            'tenant_id' => $tenant->id,
            'conversation_id' => $conversation->id,
            'direction' => MessageDirection::Outbound->value,
            'message_type' => 'text',
            'content' => 'Outbound',
            'external_message_id' => $externalMessageId,
            'status' => $status->value,
        ]);
    }
}
