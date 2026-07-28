<?php

namespace App\Modules\WhatsApp\Infrastructure\Providers\DApi;

use App\Modules\Tenant\Models\Tenant;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cliente HTTP da D-API (https://api.d-api.cloud).
 * Credenciais globais: config('whatsapp.credentials.public_key|secret_key')
 * Identificador do tenant: session_id em Tenant settings.
 */
final class DApiClient
{
    public function baseUrl(): string
    {
        return rtrim((string) config('whatsapp.d_api.base_url', 'https://api.d-api.cloud'), '/');
    }

    public function publicKey(): string
    {
        return (string) config('whatsapp.credentials.public_key');
    }

    public function secretKey(): string
    {
        return (string) config('whatsapp.credentials.secret_key');
    }

    /**
     * Identificador da sessão/conexão D-API usada no envio.
     * Na Cloud API (SaaS), connectionId e sessionId são o mesmo valor.
     */
    public function sessionId(Tenant $tenant): string
    {
        $sessionId = (string) $tenant->whatsappSetting('session_id');

        if ($sessionId !== '') {
            return $sessionId;
        }

        return (string) $tenant->whatsappSetting('connection_id');
    }

    /**
     * ID usado nos endpoints Cloud API (templates). Preferência: connection_id.
     */
    public function connectionId(Tenant $tenant): string
    {
        $connectionId = (string) $tenant->whatsappSetting('connection_id');

        return $connectionId !== '' ? $connectionId : $this->sessionId($tenant);
    }

    public function createSession(array $payload): array
    {
        return $this->request()->post($this->baseUrl().'/api/v1/sessions', $payload)->json() ?? [];
    }

    public function getSession(string $sessionId): array
    {
        return $this->request()->get($this->baseUrl().'/api/v1/sessions/'.$sessionId)->json() ?? [];
    }

    public function deleteSession(string $sessionId): array
    {
        return $this->request()->delete($this->baseUrl().'/api/v1/sessions/'.$sessionId)->json() ?? [];
    }

    public function disconnectSession(string $sessionId): array
    {
        return $this->request()->post($this->baseUrl().'/api/v1/sessions/'.$sessionId.'/disconnect')->json() ?? [];
    }

    public function updateSessionWebhook(string $sessionId, string $webhookUrl): array
    {
        return $this->request()->post($this->baseUrl().'/api/v1/sessions/'.$sessionId.'/webhook', [
            'webhookUrl' => $webhookUrl,
        ])->json() ?? [];
    }

    public function sendText(Tenant $tenant, string $to, string $text, array $extra = []): array
    {
        return $this->postMessage('/api/v1/messages/send/text', $tenant, array_merge([
            'to' => $to,
            'text' => $text,
        ], $extra));
    }

    public function sendImage(Tenant $tenant, string $to, string $image, ?string $caption = null): array
    {
        $payload = [
            'to' => $to,
            'image' => $image,
        ];

        if ($caption !== null) {
            $payload['caption'] = $caption;
        }

        return $this->postMessage('/api/v1/messages/send/image', $tenant, $payload);
    }

    public function sendDocument(Tenant $tenant, string $to, string $document, ?string $fileName = null, ?string $mimeType = null): array
    {
        $payload = [
            'to' => $to,
            'document' => $document,
        ];

        if ($fileName !== null) {
            $payload['fileName'] = $fileName;
        }

        if ($mimeType !== null) {
            $payload['mimetype'] = $mimeType;
        }

        return $this->postMessage('/api/v1/messages/send/document', $tenant, $payload);
    }

    public function sendAudio(Tenant $tenant, string $to, string $audio): array
    {
        return $this->postMessage('/api/v1/messages/send/audio', $tenant, [
            'to' => $to,
            'audio' => $audio,
        ]);
    }

    public function sendVideo(Tenant $tenant, string $to, string $video, ?string $caption = null): array
    {
        $payload = [
            'to' => $to,
            'video' => $video,
        ];

        if ($caption !== null) {
            $payload['caption'] = $caption;
        }

        return $this->postMessage('/api/v1/messages/send/video', $tenant, $payload);
    }

    public function sendTemplate(Tenant $tenant, string $to, string $templateName, string $language, array $variables): array
    {
        return $this->postMessage('/api/v1/messages/send/template', $tenant, [
            'to' => $to,
            'template' => [
                'name' => $templateName,
                'language' => $language,
                'bodyVariables' => array_values($variables),
            ],
        ]);
    }

    public function markAsRead(Tenant $tenant, string $to, string $externalMessageId): array
    {
        $payload = [
            'sessionId' => $this->sessionId($tenant),
            'to' => $to,
            'messageIds' => [$externalMessageId],
        ];

        $response = $this->request()->post($this->baseUrl().'/api/v1/chats/read', $payload);

        return $response->json() ?? [];
    }

    public function listTemplates(Tenant $tenant, bool $all = true): array
    {
        $id = $this->connectionId($tenant);
        $path = $all
            ? "/api/v1/connections/cloud-api/{$id}/templates/all"
            : "/api/v1/connections/cloud-api/{$id}/templates";

        return $this->request()->get($this->baseUrl().$path)->json() ?? [];
    }

    public function createTemplate(Tenant $tenant, array $data): array
    {
        $id = $this->connectionId($tenant);

        return $this->request()
            ->post($this->baseUrl()."/api/v1/connections/cloud-api/{$id}/templates", $data)
            ->json() ?? [];
    }

    public function deleteTemplate(Tenant $tenant, string $name): array
    {
        $id = $this->connectionId($tenant);

        return $this->request()
            ->delete($this->baseUrl()."/api/v1/connections/cloud-api/{$id}/templates/{$name}")
            ->json() ?? [];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function postMessage(string $path, Tenant $tenant, array $payload): array
    {
        $payload['sessionId'] = $this->sessionId($tenant);

        $response = $this->request()->post($this->baseUrl().$path, $payload);

        Log::info('[WhatsApp:D-API] Envio', [
            'tenant_id' => $tenant->id,
            'session_id' => $payload['sessionId'],
            'path' => $path,
            'to' => $payload['to'] ?? null,
            'response_status' => $response->status(),
        ]);

        return $response->json() ?? [];
    }

    private function request(): PendingRequest
    {
        return Http::withHeaders([
            'Authorization' => $this->secretKey(),
            'X-Public-Key' => $this->publicKey(),
            'Accept' => 'application/json',
        ])
            ->timeout(30)
            ->connectTimeout(10)
            ->acceptJson();
    }
}
