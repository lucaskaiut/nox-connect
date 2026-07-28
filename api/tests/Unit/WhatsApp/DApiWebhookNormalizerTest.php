<?php

namespace Tests\Unit\WhatsApp;

use App\Modules\Tenant\Models\Tenant;
use App\Modules\WhatsApp\Infrastructure\Providers\DApi\DApiWebhookNormalizer;
use Illuminate\Http\Request;
use Tests\TestCase;

class DApiWebhookNormalizerTest extends TestCase
{
    public function test_normalizes_messages_received(): void
    {
        $tenant = new Tenant(['uuid' => 'tenant-1']);
        $normalizer = new DApiWebhookNormalizer;

        $request = Request::create('/webhook', 'POST', [
            'event' => 'messages.received',
            'sessionId' => 'sess-1',
            'data' => [
                'id' => 'msg-1',
                'type' => 'text',
                'message' => 'Olá',
                'fromMe' => false,
                'from_name' => 'Cliente',
                'from' => [
                    'jid' => '5511999999999@s.whatsapp.net',
                    'name' => 'Cliente',
                ],
            ],
        ]);

        $result = $normalizer->normalize($tenant, $request);

        $this->assertCount(1, $result->messages);
        $this->assertSame('msg-1', $result->messages[0]->externalMessageId);
        $this->assertSame('5511999999999', $result->messages[0]->externalContactId);
        $this->assertSame('Olá', $result->messages[0]->content);
    }

    public function test_normalizes_unix_timestamp_in_seconds(): void
    {
        $tenant = new Tenant(['uuid' => 'tenant-1']);
        $normalizer = new DApiWebhookNormalizer;
        $unixSeconds = 1753651380; // 2025-07-27-ish

        $request = Request::create('/webhook', 'POST', [
            'event' => 'messages.received',
            'sessionId' => 'sess-1',
            'data' => [
                'id' => 'msg-ts',
                'type' => 'text',
                'message' => 'Agora',
                'fromMe' => false,
                'timestamp' => $unixSeconds,
                'from' => [
                    'jid' => '5511999999999@s.whatsapp.net',
                ],
            ],
        ]);

        $result = $normalizer->normalize($tenant, $request);

        $this->assertCount(1, $result->messages);
        $this->assertSame($unixSeconds, $result->messages[0]->receivedAt->timestamp);
        $this->assertSame(2025, (int) $result->messages[0]->receivedAt->format('Y'));
    }

    public function test_normalizes_message_read_status(): void
    {
        $tenant = new Tenant(['uuid' => 'tenant-1']);
        $normalizer = new DApiWebhookNormalizer;

        $request = Request::create('/webhook', 'POST', [
            'event' => 'message.read',
            'sessionId' => 'sess-1',
            'data' => [
                'message_id' => 'msg-2',
                'remote_jid' => '5511888888888@s.whatsapp.net',
            ],
        ]);

        $result = $normalizer->normalize($tenant, $request);

        $this->assertCount(1, $result->statuses);
        $this->assertSame('msg-2', $result->statuses[0]->externalMessageId);
        $this->assertSame('read', $result->statuses[0]->status);
    }
}
