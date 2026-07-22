<?php

namespace App\Modules\WhatsApp\Services;

use App\Modules\WhatsApp\Models\WhatsAppConfig;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class WhatsAppCloudApi
{
    private const BASE_URL = 'https://graph.facebook.com/v22.0';

    public function sendMessage(WhatsAppConfig $config, string $to, array $message): array
    {
        $response = Http::withToken($config->access_token)
            ->timeout(30)
            ->connectTimeout(10)
            ->post(self::BASE_URL."/{$config->phone_number_id}/messages", [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $to,
                'type' => $message['type'],
                $message['type'] => $message['payload'],
            ]);

        return $response->json();
    }

    public function sendText(WhatsAppConfig $config, string $to, string $text, ?string $previewUrl = null): array
    {
        $payload = ['body' => $text];

        if ($previewUrl !== null) {
            $payload['preview_url'] = (bool) $previewUrl;
        }

        return $this->sendMessage($config, $to, [
            'type' => 'text',
            'payload' => $payload,
        ]);
    }

    public function sendImage(WhatsAppConfig $config, string $to, string $mediaId, ?string $caption = null): array
    {
        $payload = ['id' => $mediaId];

        if ($caption !== null) {
            $payload['caption'] = $caption;
        }

        return $this->sendMessage($config, $to, [
            'type' => 'image',
            'payload' => $payload,
        ]);
    }

    public function sendDocument(WhatsAppConfig $config, string $to, string $mediaId, ?string $filename = null, ?string $caption = null): array
    {
        $payload = ['id' => $mediaId];

        if ($filename !== null) {
            $payload['filename'] = $filename;
        }

        if ($caption !== null) {
            $payload['caption'] = $caption;
        }

        return $this->sendMessage($config, $to, [
            'type' => 'document',
            'payload' => $payload,
        ]);
    }

    public function sendAudio(WhatsAppConfig $config, string $to, string $mediaId): array
    {
        return $this->sendMessage($config, $to, [
            'type' => 'audio',
            'payload' => ['id' => $mediaId],
        ]);
    }

    public function sendVideo(WhatsAppConfig $config, string $to, string $mediaId, ?string $caption = null): array
    {
        $payload = ['id' => $mediaId];

        if ($caption !== null) {
            $payload['caption'] = $caption;
        }

        return $this->sendMessage($config, $to, [
            'type' => 'video',
            'payload' => $payload,
        ]);
    }

    /**
     * @throws ConnectionException
     */
    public function verifyConnection(WhatsAppConfig $config): bool
    {
        $response = Http::withToken($config->access_token)
            ->timeout(15)
            ->connectTimeout(5)
            ->get(self::BASE_URL."/{$config->phone_number_id}");

        return $response->successful();
    }

    public function uploadMedia(WhatsAppConfig $config, string $filePath, string $mimeType): ?string
    {
        $response = Http::withToken($config->access_token)
            ->timeout(60)
            ->attach('file', file_get_contents($filePath), basename($filePath))
            ->post(self::BASE_URL."/{$config->phone_number_id}/media", [
                'messaging_product' => 'whatsapp',
                'type' => $mimeType,
            ]);

        return $response->json('id');
    }
}
