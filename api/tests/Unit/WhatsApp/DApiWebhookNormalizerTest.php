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
        $this->assertSame(config('app.timezone'), $result->messages[0]->receivedAt->timezoneName);
    }

    public function test_normalizes_image_media_url(): void
    {
        $tenant = new Tenant(['uuid' => 'tenant-1']);
        $normalizer = new DApiWebhookNormalizer;

        $request = Request::create('/webhook', 'POST', [
            'event' => 'messages.received',
            'sessionId' => 'sess-1',
            'data' => [
                'id' => 'msg-image',
                'type' => 'image',
                'message' => null,
                'fromMe' => false,
                'from_name' => 'Cliente',
                'from' => ['jid' => '5511999999999@s.whatsapp.net'],
                'media_url' => 'https://cdn.example.com/photo.jpg',
                'media_data' => [
                    'url' => 'https://cdn.example.com/photo.jpg',
                    'mimetype' => 'image/jpeg',
                    'file_length' => 1234,
                ],
            ],
        ]);

        $result = $normalizer->normalize($tenant, $request);

        $this->assertCount(1, $result->messages);
        $this->assertSame('image', $result->messages[0]->messageType);
        $this->assertSame('inbound', $result->messages[0]->direction);
        $this->assertSame('https://cdn.example.com/photo.jpg', $result->messages[0]->media['url']);
        $this->assertSame('image/jpeg', $result->messages[0]->media['mime_type']);
    }

    public function test_normalizes_from_me_audio_as_outbound(): void
    {
        $tenant = new Tenant(['uuid' => 'tenant-1']);
        $normalizer = new DApiWebhookNormalizer;

        $request = Request::create('/webhook', 'POST', [
            'event' => 'messages.received',
            'sessionId' => 'sess-1',
            'data' => [
                'id' => 'msg-audio',
                'type' => 'audio',
                'message' => null,
                'fromMe' => true,
                'coexistence' => 'echo',
                'from' => ['jid' => '5511888888888@s.whatsapp.net'],
                'to' => ['jid' => '5511999999999@s.whatsapp.net'],
                'media_url' => 'https://cdn.example.com/voice.ogg',
                'media_data' => [
                    'url' => 'https://cdn.example.com/voice.ogg',
                    'mimetype' => 'audio/ogg',
                    'file_length' => 5102,
                ],
            ],
        ]);

        $result = $normalizer->normalize($tenant, $request);

        $this->assertCount(1, $result->messages);
        $this->assertSame('audio', $result->messages[0]->messageType);
        $this->assertSame('outbound', $result->messages[0]->direction);
        $this->assertSame('5511999999999', $result->messages[0]->externalContactId);
        $this->assertSame('https://cdn.example.com/voice.ogg', $result->messages[0]->media['url']);
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
