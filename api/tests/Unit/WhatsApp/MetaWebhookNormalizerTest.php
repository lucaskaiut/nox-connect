<?php

namespace Tests\Unit\WhatsApp;

use App\Modules\Tenant\Models\Tenant;
use App\Modules\WhatsApp\DTOs\IncomingMessageDTO;
use App\Modules\WhatsApp\DTOs\MessageStatusUpdateDTO;
use App\Modules\WhatsApp\DTOs\WebhookChallengeDTO;
use App\Modules\WhatsApp\Infrastructure\Providers\Meta\MetaWebhookNormalizer;
use Illuminate\Http\Request;
use Tests\TestCase;

class MetaWebhookNormalizerTest extends TestCase
{
    public function test_verifies_hub_challenge_from_tenant_settings(): void
    {
        $tenant = new Tenant([
            'settings' => [
                'whatsapp' => [
                    'webhook_verify_token' => 'secret-token',
                ],
            ],
        ]);

        $request = Request::create('/webhooks/whatsapp/uuid', 'GET', [
            'hub_mode' => 'subscribe',
            'hub_verify_token' => 'secret-token',
            'hub_challenge' => '12345',
        ]);

        $result = app(MetaWebhookNormalizer::class)->verify($tenant, $request);

        $this->assertInstanceOf(WebhookChallengeDTO::class, $result);
        $this->assertTrue($result->valid);
        $this->assertSame('12345', $result->challenge);
    }

    public function test_normalizes_incoming_message_and_status(): void
    {
        $tenant = new Tenant(['settings' => ['whatsapp' => []]]);

        $payload = [
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'messaging_product' => 'whatsapp',
                        'contacts' => [[
                            'wa_id' => '5511999999999',
                            'profile' => ['name' => 'Cliente'],
                        ]],
                        'messages' => [[
                            'from' => '5511999999999',
                            'id' => 'wamid.ABC',
                            'type' => 'text',
                            'text' => ['body' => 'Olá'],
                        ]],
                        'statuses' => [[
                            'id' => 'wamid.OUT',
                            'status' => 'delivered',
                        ]],
                    ],
                ]],
            ]],
        ];

        $request = Request::create('/webhooks/whatsapp/uuid', 'POST', $payload);
        $result = app(MetaWebhookNormalizer::class)->normalize($tenant, $request);

        $this->assertCount(1, $result->messages);
        $this->assertInstanceOf(IncomingMessageDTO::class, $result->messages[0]);
        $this->assertSame('wamid.ABC', $result->messages[0]->externalMessageId);
        $this->assertSame('Olá', $result->messages[0]->content);

        $this->assertCount(1, $result->statuses);
        $this->assertInstanceOf(MessageStatusUpdateDTO::class, $result->statuses[0]);
        $this->assertSame('delivered', $result->statuses[0]->status);
    }
}
