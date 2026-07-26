<?php

namespace App\Modules\WhatsApp\Infrastructure\Providers\DApi;

use App\Modules\Tenant\Models\Tenant;
use App\Modules\WhatsApp\Contracts\WhatsAppProvider;
use App\Modules\WhatsApp\Contracts\WhatsAppTemplateCatalog;
use App\Modules\WhatsApp\DTOs\ConnectionResultDTO;
use App\Modules\WhatsApp\DTOs\ConnectionStatusDTO;
use App\Modules\WhatsApp\DTOs\CreateTemplateDTO;
use App\Modules\WhatsApp\DTOs\DeleteTemplatesDTO;
use App\Modules\WhatsApp\DTOs\MessageResultDTO;
use App\Modules\WhatsApp\DTOs\ProviderErrorDTO;
use App\Modules\WhatsApp\DTOs\SendAudioDTO;
use App\Modules\WhatsApp\DTOs\SendDocumentDTO;
use App\Modules\WhatsApp\DTOs\SendImageDTO;
use App\Modules\WhatsApp\DTOs\SendTemplateDTO;
use App\Modules\WhatsApp\DTOs\SendTextMessageDTO;
use App\Modules\WhatsApp\DTOs\SendVideoDTO;
use App\Modules\WhatsApp\DTOs\TemplateDTO;
use App\Modules\WhatsApp\DTOs\TemplateListResultDTO;
use App\Modules\WhatsApp\DTOs\UpdateTemplateDTO;
use App\Modules\WhatsApp\Enums\WhatsAppProviderKey;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class DApiProvider implements WhatsAppProvider, WhatsAppTemplateCatalog
{
    public function __construct(
        private readonly DApiClient $client,
    ) {}

    public function key(): string
    {
        return WhatsAppProviderKey::DApi->value;
    }

    public function createConnection(Tenant $tenant, array $input = []): ConnectionResultDTO
    {
        if (blank($this->client->secretKey())) {
            throw new InvalidArgumentException('Credencial global WHATSAPP_SECRET_KEY não configurada.');
        }

        $sessionId = (string) ($input['session_id'] ?? '');
        $webhookUrl = url('/api/webhooks/whatsapp/'.$tenant->uuid);

        try {
            if ($sessionId === '') {
                $sessionId = 'nox-'.$tenant->uuid;
                $response = $this->client->createSession([
                    'sessionId' => $sessionId,
                    'type' => $input['type'] ?? 'unofficial',
                    'webhookUrl' => $webhookUrl,
                    'connectionMode' => $input['connection_mode'] ?? 'qr',
                    'metadata' => [
                        'tenant_uuid' => $tenant->uuid,
                        'public_key' => $this->client->publicKey(),
                    ],
                ]);
            } else {
                $response = $this->client->getSession($sessionId);
            }
        } catch (\Throwable $e) {
            return new ConnectionResultDTO(
                settings: [],
                connected: false,
                message: "Erro ao conectar D-API: {$e->getMessage()}",
            );
        }

        if (($response['success'] ?? true) === false) {
            return new ConnectionResultDTO(
                settings: [],
                connected: false,
                message: (string) ($response['error'] ?? 'Falha ao criar/validar sessão na D-API.'),
            );
        }

        $resolvedSessionId = (string) (
            data_get($response, 'data.sessionId')
            ?? data_get($response, 'data.session_id')
            ?? data_get($response, 'data.id')
            ?? $sessionId
        );

        $connectionId = (string) (
            $input['connection_id']
            ?? data_get($response, 'data.connectionId')
            ?? data_get($response, 'data.connection_id')
            ?? $resolvedSessionId
        );

        return new ConnectionResultDTO(
            settings: [
                'session_id' => $resolvedSessionId,
                'connection_id' => $connectionId,
                'webhook_verify_token' => $input['webhook_verify_token'] ?? Str::random(40),
                'status' => 'connected',
                'connected_at' => now()->toIso8601String(),
            ],
            connected: true,
            message: 'Conexão WhatsApp (D-API) estabelecida com sucesso.',
        );
    }

    public function disconnectConnection(Tenant $tenant): void
    {
        $sessionId = $this->client->sessionId($tenant);

        if ($sessionId === '') {
            return;
        }

        try {
            $this->client->disconnectSession($sessionId);
        } catch (\Throwable) {
            // Best-effort: limpeza local ocorre no controller.
        }
    }

    public function getConnectionStatus(Tenant $tenant): ConnectionStatusDTO
    {
        if (! $tenant->isWhatsappConnected()) {
            return new ConnectionStatusDTO(connected: false, message: 'WhatsApp não conectado para este tenant.');
        }

        try {
            $response = $this->client->getSession($this->client->sessionId($tenant));
            $ok = ($response['success'] ?? true) !== false
                && ! isset($response['error']);

            return new ConnectionStatusDTO(
                connected: $ok,
                message: $ok ? 'Conexão ativa (D-API).' : (string) ($response['error'] ?? 'Sessão inacessível.'),
            );
        } catch (\Throwable $e) {
            return new ConnectionStatusDTO(connected: false, message: $e->getMessage());
        }
    }

    public function sendText(SendTextMessageDTO $dto): MessageResultDTO
    {
        $extra = [];

        if ($dto->previewUrl !== null) {
            $extra['linkPreview'] = true;
            $extra['linkPreviewUrl'] = $dto->previewUrl;
        }

        return $this->mapSendResult($this->client->sendText($dto->tenant, $dto->to, $dto->body, $extra));
    }

    public function sendImage(SendImageDTO $dto): MessageResultDTO
    {
        return $this->mapSendResult($this->client->sendImage(
            $dto->tenant,
            $dto->to,
            $dto->mediaId,
            $dto->caption,
        ));
    }

    public function sendDocument(SendDocumentDTO $dto): MessageResultDTO
    {
        return $this->mapSendResult($this->client->sendDocument(
            $dto->tenant,
            $dto->to,
            $dto->mediaId,
            $dto->filename,
        ));
    }

    public function sendAudio(SendAudioDTO $dto): MessageResultDTO
    {
        return $this->mapSendResult($this->client->sendAudio($dto->tenant, $dto->to, $dto->mediaId));
    }

    public function sendVideo(SendVideoDTO $dto): MessageResultDTO
    {
        return $this->mapSendResult($this->client->sendVideo(
            $dto->tenant,
            $dto->to,
            $dto->mediaId,
            $dto->caption,
        ));
    }

    public function sendTemplate(SendTemplateDTO $dto): MessageResultDTO
    {
        return $this->mapSendResult($this->client->sendTemplate(
            $dto->tenant,
            $dto->to,
            $dto->templateName,
            $dto->language,
            $dto->variables,
        ));
    }

    public function uploadMedia(Tenant $tenant, string $filePath, string $mimeType): ?string
    {
        unset($tenant);

        if (! is_readable($filePath)) {
            return null;
        }

        $contents = file_get_contents($filePath);

        if ($contents === false) {
            return null;
        }

        return 'data:'.$mimeType.';base64,'.base64_encode($contents);
    }

    public function markAsRead(Tenant $tenant, string $externalMessageId): void
    {
        // D-API exige `to` + messageIds; sem destinatário no contrato atual, no-op.
        unset($tenant, $externalMessageId);
    }

    public function listTemplates(Tenant $tenant, array $params = []): TemplateListResultDTO
    {
        unset($params);

        $response = $this->client->listTemplates($tenant, all: true);

        if (($response['success'] ?? true) === false) {
            return new TemplateListResultDTO(
                error: new ProviderErrorDTO(
                    message: (string) ($response['error'] ?? 'Erro ao listar templates'),
                    code: isset($response['code']) ? (string) $response['code'] : null,
                    raw: $response,
                ),
                raw: $response,
            );
        }

        $items = $response['data'] ?? [];

        if (! is_array($items)) {
            $items = [];
        }

        // Envelope pode vir como { data: { data: [...] } } em alguns proxies.
        if (isset($items['data']) && is_array($items['data'])) {
            $items = $items['data'];
        }

        $templates = [];

        foreach ($items as $item) {
            if (is_array($item)) {
                $templates[] = TemplateDTO::fromProviderArray($item);
            }
        }

        return new TemplateListResultDTO(
            templates: $templates,
            paging: $response['paging'] ?? null,
            raw: $response,
        );
    }

    public function getTemplate(Tenant $tenant, string $externalTemplateId, ?string $fields = null): TemplateDTO
    {
        unset($fields);

        $list = $this->listTemplates($tenant);

        foreach ($list->templates as $template) {
            if ($template->id === $externalTemplateId || $template->name === $externalTemplateId) {
                return $template;
            }
        }

        return TemplateDTO::fromProviderArray(['id' => $externalTemplateId]);
    }

    public function createTemplate(Tenant $tenant, CreateTemplateDTO $dto): array
    {
        $attrs = $dto->attributes;
        $bodyText = (string) ($attrs['bodyText'] ?? $this->extractBodyText($attrs['components'] ?? []) ?? '');

        $payload = [
            'name' => (string) ($attrs['name'] ?? ''),
            'category' => strtoupper((string) ($attrs['category'] ?? 'UTILITY')),
            'language' => (string) ($attrs['language'] ?? 'pt_BR'),
            'bodyText' => $bodyText,
        ];

        if (! empty($attrs['bodyExample']) && is_array($attrs['bodyExample'])) {
            $payload['bodyExample'] = $attrs['bodyExample'];
        } else {
            $examples = $this->extractBodyExamples($attrs['components'] ?? []);
            if ($examples !== []) {
                $payload['bodyExample'] = $examples;
            }
        }

        return $this->client->createTemplate($tenant, $payload);
    }

    public function updateTemplate(Tenant $tenant, string $externalTemplateId, UpdateTemplateDTO $dto): array
    {
        unset($tenant, $externalTemplateId, $dto);

        return [
            'success' => false,
            'error' => 'A D-API não expõe atualização de template; exclua e recrie.',
        ];
    }

    public function deleteTemplates(Tenant $tenant, DeleteTemplatesDTO $dto): array
    {
        $name = (string) ($dto->criteria['name'] ?? '');

        if ($name === '') {
            return ['success' => false, 'error' => 'Informe o nome do template para exclusão.'];
        }

        return $this->client->deleteTemplate($tenant, $name);
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function mapSendResult(array $response): MessageResultDTO
    {
        $externalId = data_get($response, 'data.id')
            ?? data_get($response, 'data.messageId')
            ?? data_get($response, 'data.message_id')
            ?? data_get($response, 'data.key.id')
            ?? data_get($response, 'messages.0.id');

        if (is_string($externalId) && $externalId !== '' && ($response['success'] ?? true) !== false) {
            return MessageResultDTO::success($externalId, $response);
        }

        $error = isset($response['error'])
            ? new ProviderErrorDTO(
                message: is_string($response['error'])
                    ? $response['error']
                    : (string) data_get($response, 'error.message', 'Erro desconhecido do provedor'),
                code: isset($response['statusCode']) ? (string) $response['statusCode'] : (isset($response['code']) ? (string) $response['code'] : null),
                raw: is_array($response['error'] ?? null) ? $response['error'] : $response,
            )
            : new ProviderErrorDTO(message: 'Erro desconhecido do provedor', raw: $response);

        return MessageResultDTO::failure($error, $response);
    }

    /**
     * @param  mixed  $components
     */
    private function extractBodyText(mixed $components): ?string
    {
        if (! is_array($components)) {
            return null;
        }

        foreach ($components as $component) {
            if (! is_array($component)) {
                continue;
            }

            $type = strtoupper((string) ($component['type'] ?? ''));

            if (in_array($type, ['BODY', 'body'], true)) {
                return isset($component['text']) ? (string) $component['text'] : null;
            }
        }

        return null;
    }

    /**
     * @param  mixed  $components
     * @return list<string>
     */
    private function extractBodyExamples(mixed $components): array
    {
        if (! is_array($components)) {
            return [];
        }

        foreach ($components as $component) {
            if (! is_array($component)) {
                continue;
            }

            $type = strtoupper((string) ($component['type'] ?? ''));

            if ($type !== 'BODY') {
                continue;
            }

            $named = data_get($component, 'example.body_text_named_params');

            if (is_array($named)) {
                return array_values(array_map(
                    fn ($p) => (string) (is_array($p) ? ($p['example'] ?? '') : $p),
                    $named,
                ));
            }

            $bodyText = data_get($component, 'example.body_text.0');

            if (is_array($bodyText)) {
                return array_values(array_map('strval', $bodyText));
            }
        }

        return [];
    }
}
