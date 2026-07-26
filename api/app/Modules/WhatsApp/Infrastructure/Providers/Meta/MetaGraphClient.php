<?php

namespace App\Modules\WhatsApp\Infrastructure\Providers\Meta;

use App\Modules\Tenant\Models\Tenant;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cliente HTTP exclusivo da Meta Graph API.
 * Credenciais: config('whatsapp.credentials.*')
 * Identificadores do tenant: $tenant->whatsappSetting(...)
 */
final class MetaGraphClient
{
    private const BASE_URL = 'https://graph.facebook.com/v22.0';

    public function accessToken(): string
    {
        return (string) config('whatsapp.credentials.access_token');
    }

    public function channelId(Tenant $tenant): string
    {
        return (string) $tenant->whatsappSetting('channel_id');
    }

    public function accountId(Tenant $tenant): string
    {
        return (string) $tenant->whatsappSetting('account_id');
    }

    public function sendMessage(Tenant $tenant, string $to, array $message): array
    {
        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => $message['type'],
            $message['type'] => $message['payload'],
        ];

        $channelId = $this->channelId($tenant);

        $response = Http::withToken($this->accessToken())
            ->timeout(30)
            ->connectTimeout(10)
            ->post(self::BASE_URL.'/'.$channelId.'/messages', $payload);

        Log::info('[WhatsApp:Meta] Envio de mensagem', [
            'tenant_id' => $tenant->id,
            'channel_id' => $channelId,
            'to' => $to,
            'type' => $message['type'],
            'response_status' => $response->status(),
        ]);

        return $response->json() ?? [];
    }

    public function sendTemplate(Tenant $tenant, string $to, string $templateName, string $language, array $variables): array
    {
        $parameters = array_map(fn (string $value) => [
            'type' => 'text',
            'text' => $value,
        ], $variables);

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => ['code' => $language],
                'components' => [
                    [
                        'type' => 'body',
                        'parameters' => $parameters,
                    ],
                ],
            ],
        ];

        $channelId = $this->channelId($tenant);

        $response = Http::withToken($this->accessToken())
            ->timeout(30)
            ->connectTimeout(10)
            ->post(self::BASE_URL.'/'.$channelId.'/messages', $payload);

        Log::info('[WhatsApp:Meta] Envio de template', [
            'tenant_id' => $tenant->id,
            'channel_id' => $channelId,
            'template_name' => $templateName,
            'response_status' => $response->status(),
        ]);

        return $response->json() ?? [];
    }

    /**
     * @throws ConnectionException
     */
    public function verifyChannel(string $channelId): bool
    {
        $response = Http::withToken($this->accessToken())
            ->timeout(15)
            ->connectTimeout(5)
            ->get(self::BASE_URL.'/'.$channelId);

        return $response->successful();
    }

    public function uploadMedia(Tenant $tenant, string $filePath, string $mimeType): ?string
    {
        $response = Http::withToken($this->accessToken())
            ->timeout(60)
            ->attach('file', file_get_contents($filePath), basename($filePath))
            ->post(self::BASE_URL.'/'.$this->channelId($tenant).'/media', [
                'messaging_product' => 'whatsapp',
                'type' => $mimeType,
            ]);

        return $response->json('id');
    }

    public function markAsRead(Tenant $tenant, string $externalMessageId): void
    {
        Http::withToken($this->accessToken())
            ->timeout(15)
            ->connectTimeout(5)
            ->post(self::BASE_URL.'/'.$this->channelId($tenant).'/messages', [
                'messaging_product' => 'whatsapp',
                'status' => 'read',
                'message_id' => $externalMessageId,
            ]);
    }

    public function listMessageTemplates(Tenant $tenant, array $query = []): array
    {
        $response = Http::withToken($this->accessToken())
            ->timeout(30)
            ->connectTimeout(10)
            ->get(self::BASE_URL.'/'.$this->accountId($tenant).'/message_templates', $query);

        return $response->json() ?? [];
    }

    public function getMessageTemplate(string $templateId, array $query = []): array
    {
        $response = Http::withToken($this->accessToken())
            ->timeout(30)
            ->connectTimeout(10)
            ->get(self::BASE_URL.'/'.$templateId, $query);

        return $response->json() ?? [];
    }

    public function createMessageTemplate(Tenant $tenant, array $data): array
    {
        $response = Http::withToken($this->accessToken())
            ->timeout(30)
            ->connectTimeout(10)
            ->post(self::BASE_URL.'/'.$this->accountId($tenant).'/message_templates', $data);

        return $response->json() ?? [];
    }

    public function updateMessageTemplate(string $templateId, array $data): array
    {
        $response = Http::withToken($this->accessToken())
            ->timeout(30)
            ->connectTimeout(10)
            ->post(self::BASE_URL.'/'.$templateId, $data);

        return $response->json() ?? [];
    }

    public function deleteMessageTemplates(Tenant $tenant, array $query): array
    {
        $response = Http::withToken($this->accessToken())
            ->timeout(30)
            ->connectTimeout(10)
            ->delete(self::BASE_URL.'/'.$this->accountId($tenant).'/message_templates', $query);

        return $response->json() ?? [];
    }
}
