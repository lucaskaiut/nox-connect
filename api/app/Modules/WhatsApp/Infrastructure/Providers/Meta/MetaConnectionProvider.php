<?php

namespace App\Modules\WhatsApp\Infrastructure\Providers\Meta;

use App\Modules\Tenant\Models\Tenant;
use App\Modules\WhatsApp\Contracts\WhatsAppConnectionProvider;
use App\Modules\WhatsApp\DTOs\ConnectionInitializationDTO;
use App\Modules\WhatsApp\DTOs\ConnectionResultDTO;
use App\Modules\WhatsApp\DTOs\ConnectionStatusDTO;
use App\Modules\WhatsApp\Enums\WhatsAppProviderKey;
use Illuminate\Support\Str;

/**
 * Estratégia de conexão via formulário manual (account_id + channel_id).
 */
final class MetaConnectionProvider implements WhatsAppConnectionProvider
{
    public function __construct(
        private readonly MetaGraphClient $client,
    ) {}

    public function key(): string
    {
        return WhatsAppProviderKey::Meta->value;
    }

    public function getConfiguration(): array
    {
        return [
            'fields' => [
                ['name' => 'account_id', 'label' => 'ID da conta comercial', 'required' => true],
                ['name' => 'channel_id', 'label' => 'ID do canal', 'required' => true],
            ],
        ];
    }

    public function initialize(Tenant $tenant): ConnectionInitializationDTO
    {
        unset($tenant);

        return new ConnectionInitializationDTO(
            type: 'form',
            provider: $this->key(),
            configuration: $this->getConfiguration(),
        );
    }

    public function complete(Tenant $tenant, array $payload): ConnectionResultDTO
    {
        unset($tenant);

        $accountId = (string) ($payload['account_id'] ?? '');
        $channelId = (string) ($payload['channel_id'] ?? '');

        if ($accountId === '' || $channelId === '') {
            return new ConnectionResultDTO(
                settings: [],
                connected: false,
                message: 'account_id e channel_id são obrigatórios.',
            );
        }

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
                message: 'Falha ao validar o canal com as credenciais globais.',
            );
        }

        return new ConnectionResultDTO(
            settings: [
                'provider' => $this->key(),
                'account_id' => $accountId,
                'channel_id' => $channelId,
                'webhook_verify_token' => $payload['webhook_verify_token'] ?? Str::random(40),
                'status' => 'connected',
                'connected_at' => now()->toIso8601String(),
            ],
            connected: true,
            message: 'WhatsApp conectado via Meta.',
        );
    }

    public function status(Tenant $tenant): ConnectionStatusDTO
    {
        if (! $tenant->isWhatsappConnected()) {
            return new ConnectionStatusDTO(connected: false, message: 'WhatsApp não conectado.');
        }

        try {
            $ok = $this->client->verifyChannel((string) $tenant->whatsappSetting('channel_id'));

            return new ConnectionStatusDTO(
                connected: $ok,
                message: $ok ? 'Conexão ativa.' : 'Canal inacessível.',
            );
        } catch (\Throwable $e) {
            return new ConnectionStatusDTO(connected: false, message: $e->getMessage());
        }
    }
}
