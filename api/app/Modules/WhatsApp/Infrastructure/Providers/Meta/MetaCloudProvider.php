<?php

namespace App\Modules\WhatsApp\Infrastructure\Providers\Meta;

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
use App\Modules\WhatsApp\Services\WhatsAppConnectionOwnership;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class MetaCloudProvider implements WhatsAppProvider, WhatsAppTemplateCatalog
{
    public function __construct(
        private readonly MetaGraphClient $client,
    ) {}

    public function key(): string
    {
        return WhatsAppProviderKey::Meta->value;
    }

    public function createConnection(Tenant $tenant, array $input = []): ConnectionResultDTO
    {
        $accountId = (string) ($input['account_id'] ?? '');
        $channelId = (string) ($input['channel_id'] ?? '');

        if ($accountId === '' || $channelId === '') {
            throw new InvalidArgumentException('account_id e channel_id são obrigatórios para conectar via Meta.');
        }

        if (blank(config('whatsapp.credentials.access_token'))) {
            throw new InvalidArgumentException('Credencial global WHATSAPP_ACCESS_TOKEN não configurada.');
        }

        // SEC-04 (mitigação local): bloquear canal já vinculado a outro tenant.
        // Modelo atual = BSP com token global; self-service ainda alcança canais do token
        // até haver Embedded Signup / provisioning operator-only (AP-03).
        WhatsAppConnectionOwnership::assertExternalIdAvailable($tenant, $channelId, 'channel_id');

        try {
            $ok = $this->client->verifyChannel($channelId);
        } catch (\Throwable $e) {
            return new ConnectionResultDTO(
                settings: [],
                connected: false,
                message: "Erro ao validar canal: {$e->getMessage()}",
            );
        }

        if (! $ok) {
            return new ConnectionResultDTO(
                settings: [],
                connected: false,
                message: 'Falha ao validar o canal com as credenciais globais. Verifique account_id/channel_id.',
            );
        }

        return new ConnectionResultDTO(
            settings: [
                'account_id' => $accountId,
                'channel_id' => $channelId,
                'webhook_verify_token' => $input['webhook_verify_token'] ?? Str::random(40),
                'status' => 'connected',
                'connected_at' => now()->toIso8601String(),
            ],
            connected: true,
            message: 'Conexão WhatsApp estabelecida com sucesso.',
        );
    }

    public function disconnectConnection(Tenant $tenant): void
    {
        // Meta Cloud API não exige teardown remoto obrigatório para este fluxo.
    }

    public function getConnectionStatus(Tenant $tenant): ConnectionStatusDTO
    {
        if (! $tenant->isWhatsappConnected()) {
            return new ConnectionStatusDTO(connected: false, message: 'WhatsApp não conectado para este tenant.');
        }

        try {
            $ok = $this->client->verifyChannel($this->client->channelId($tenant));

            return new ConnectionStatusDTO(
                connected: $ok,
                message: $ok ? 'Conexão ativa.' : 'Canal inacessível com as credenciais atuais.',
            );
        } catch (\Throwable $e) {
            return new ConnectionStatusDTO(connected: false, message: $e->getMessage());
        }
    }

    public function sendText(SendTextMessageDTO $dto): MessageResultDTO
    {
        $payload = ['body' => $dto->body];

        if ($dto->previewUrl !== null) {
            $payload['preview_url'] = $dto->previewUrl;
        }

        return $this->mapSendResult($this->client->sendMessage($dto->tenant, $dto->to, [
            'type' => 'text',
            'payload' => $payload,
        ]));
    }

    public function sendImage(SendImageDTO $dto): MessageResultDTO
    {
        $payload = ['id' => $dto->mediaId];

        if ($dto->caption !== null) {
            $payload['caption'] = $dto->caption;
        }

        return $this->mapSendResult($this->client->sendMessage($dto->tenant, $dto->to, [
            'type' => 'image',
            'payload' => $payload,
        ]));
    }

    public function sendDocument(SendDocumentDTO $dto): MessageResultDTO
    {
        $payload = ['id' => $dto->mediaId];

        if ($dto->filename !== null) {
            $payload['filename'] = $dto->filename;
        }

        if ($dto->caption !== null) {
            $payload['caption'] = $dto->caption;
        }

        return $this->mapSendResult($this->client->sendMessage($dto->tenant, $dto->to, [
            'type' => 'document',
            'payload' => $payload,
        ]));
    }

    public function sendAudio(SendAudioDTO $dto): MessageResultDTO
    {
        return $this->mapSendResult($this->client->sendMessage($dto->tenant, $dto->to, [
            'type' => 'audio',
            'payload' => ['id' => $dto->mediaId],
        ]));
    }

    public function sendVideo(SendVideoDTO $dto): MessageResultDTO
    {
        $payload = ['id' => $dto->mediaId];

        if ($dto->caption !== null) {
            $payload['caption'] = $dto->caption;
        }

        return $this->mapSendResult($this->client->sendMessage($dto->tenant, $dto->to, [
            'type' => 'video',
            'payload' => $payload,
        ]));
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
        return $this->client->uploadMedia($tenant, $filePath, $mimeType);
    }

    public function markAsRead(Tenant $tenant, string $externalMessageId): void
    {
        $this->client->markAsRead($tenant, $externalMessageId);
    }

    public function listTemplates(Tenant $tenant, array $params = []): TemplateListResultDTO
    {
        $query = [];

        foreach (['fields', 'limit', 'after', 'before'] as $key) {
            if (isset($params[$key])) {
                $query[$key] = $params[$key];
            }
        }

        $response = $this->client->listMessageTemplates($tenant, $query);

        if (! empty($response['error'])) {
            return new TemplateListResultDTO(
                error: ProviderErrorDTO::fromArray($response['error']),
                raw: $response,
            );
        }

        $templates = array_map(
            fn (array $item): TemplateDTO => TemplateDTO::fromProviderArray($item),
            $response['data'] ?? [],
        );

        return new TemplateListResultDTO(
            templates: $templates,
            paging: $response['paging'] ?? null,
            raw: $response,
        );
    }

    public function getTemplate(Tenant $tenant, string $externalTemplateId, ?string $fields = null): TemplateDTO
    {
        unset($tenant);
        $query = [];

        if ($fields !== null) {
            $query['fields'] = $fields;
        }

        return TemplateDTO::fromProviderArray($this->client->getMessageTemplate($externalTemplateId, $query));
    }

    public function createTemplate(Tenant $tenant, CreateTemplateDTO $dto): array
    {
        return $this->client->createMessageTemplate($tenant, $dto->attributes);
    }

    public function updateTemplate(Tenant $tenant, string $externalTemplateId, UpdateTemplateDTO $dto): array
    {
        unset($tenant);

        return $this->client->updateMessageTemplate($externalTemplateId, $dto->attributes);
    }

    public function deleteTemplates(Tenant $tenant, DeleteTemplatesDTO $dto): array
    {
        $query = [];

        foreach (['name', 'hsm_id', 'hsm_ids'] as $key) {
            if (isset($dto->criteria[$key])) {
                $query[$key] = $dto->criteria[$key];
            }
        }

        return $this->client->deleteMessageTemplates($tenant, $query);
    }

    private function mapSendResult(array $response): MessageResultDTO
    {
        $externalId = $response['messages'][0]['id'] ?? null;

        if (is_string($externalId) && $externalId !== '') {
            return MessageResultDTO::success($externalId, $response);
        }

        $error = isset($response['error']) && is_array($response['error'])
            ? ProviderErrorDTO::fromArray($response['error'])
            : new ProviderErrorDTO(message: 'Erro desconhecido do provedor');

        return MessageResultDTO::failure($error, $response);
    }
}
