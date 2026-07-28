<?php

namespace App\Modules\WhatsApp\Infrastructure\Providers\DApi;

use App\Modules\Tenant\Models\Tenant;
use App\Modules\WhatsApp\Contracts\WhatsAppConnectionProvider;
use App\Modules\WhatsApp\DTOs\ConnectionInitializationDTO;
use App\Modules\WhatsApp\DTOs\ConnectionResultDTO;
use App\Modules\WhatsApp\DTOs\ConnectionStatusDTO;
use App\Modules\WhatsApp\Enums\WhatsAppProviderKey;
use App\Modules\WhatsApp\Services\WhatsAppConnectionOwnership;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Fluxo de conexão D-API via SDK (publishable key + popup Embedded Signup).
 * Credencial secreta permanece apenas no backend (DApiClient).
 *
 * Webhook público (quando disponível) vai no SDK; complete() reforça o registro via API secreta.
 */
final class DApiConnectionProvider implements WhatsAppConnectionProvider
{
    public function __construct(
        private readonly DApiClient $client,
    ) {}

    public function key(): string
    {
        return WhatsAppProviderKey::DApi->value;
    }

    public function getConfiguration(): array
    {
        $mode = strtolower((string) config('whatsapp.d_api.connect_mode', 'standard'));

        if (! in_array($mode, ['standard', 'coexistence'], true)) {
            $mode = 'standard';
        }

        return [
            'publishable_key' => (string) config('whatsapp.credentials.public_key'),
            'connect_base_url' => (string) config('whatsapp.d_api.connect_base_url', 'https://connect.d-api.cloud'),
            'mode' => $mode,
            'keep_popup_on_error' => (bool) config('whatsapp.d_api.keep_popup_on_error', false),
        ];
    }

    public function initialize(Tenant $tenant): ConnectionInitializationDTO
    {
        $publishableKey = (string) config('whatsapp.credentials.public_key');

        if ($publishableKey === '') {
            throw new InvalidArgumentException('WHATSAPP_PUBLIC_KEY (publishable key) não configurada.');
        }

        $webhookUrl = $this->publicWebhookUrl($tenant);
        $connectionNonce = WhatsAppConnectionOwnership::beginConnect($tenant);

        $configuration = $this->getConfiguration();

        Log::info('[WhatsApp:D-API] initialize connection', [
            'tenant_id' => $tenant->id,
            'tenant_uuid' => $tenant->uuid,
            'publishable_key_prefix' => substr($publishableKey, 0, 12).'…',
            'connect_base_url' => $configuration['connect_base_url'] ?? null,
            'webhook_base_url_config' => config('whatsapp.d_api.webhook_base_url'),
            'resolved_webhook_url' => $webhookUrl,
            'webhook_in_sdk' => $webhookUrl !== null,
            'mode' => $configuration['mode'] ?? 'standard',
        ]);

        return new ConnectionInitializationDTO(
            type: 'sdk',
            provider: $this->key(),
            configuration: [
                ...$configuration,
                'webhook_url' => $webhookUrl,
                'connection_nonce' => $connectionNonce,
            ],
            webhookUrl: $webhookUrl,
        );
    }

    public function complete(Tenant $tenant, array $payload): ConnectionResultDTO
    {
        $connectionId = (string) ($payload['connection_id'] ?? $payload['connectionId'] ?? '');
        $phoneNumber = $payload['phone_number'] ?? $payload['phoneNumber'] ?? null;
        $status = (string) ($payload['status'] ?? 'connected');
        $nonce = isset($payload['connection_nonce']) ? (string) $payload['connection_nonce'] : null;

        Log::info('[WhatsApp:D-API] complete connection payload', [
            'tenant_id' => $tenant->id,
            'connection_id' => $connectionId !== '' ? $connectionId : null,
            'phone_number' => $phoneNumber,
            'status' => $status,
            'payload_keys' => array_keys($payload),
        ]);

        if ($connectionId === '') {
            Log::warning('[WhatsApp:D-API] complete sem connection_id', [
                'tenant_id' => $tenant->id,
                'payload_keys' => array_keys($payload),
            ]);

            return new ConnectionResultDTO(
                settings: [],
                connected: false,
                message: 'connection_id é obrigatório para concluir a conexão D-API.',
            );
        }

        // SEC-03: só aceita complete após initialize deste tenant + ID não claimado.
        WhatsAppConnectionOwnership::assertConnectNonce($tenant, $nonce);
        WhatsAppConnectionOwnership::assertExternalIdAvailable($tenant, $connectionId);

        try {
            $session = $this->client->getSession($connectionId);
            $existingWebhook = (string) (
                data_get($session, 'data.webhookUrl')
                ?? data_get($session, 'data.webhook_url')
                ?? data_get($session, 'webhookUrl')
                ?? ''
            );
            WhatsAppConnectionOwnership::assertWebhookBelongsToTenant(
                $tenant,
                $existingWebhook !== '' ? $existingWebhook : null,
            );
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::warning('[WhatsApp:D-API] getSession pré-complete falhou', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
            ]);

            return new ConnectionResultDTO(
                settings: [],
                connected: false,
                message: 'Não foi possível validar a sessão D-API antes de concluir.',
            );
        }

        $webhookUrl = $this->publicWebhookUrl($tenant);

        if ($webhookUrl !== null) {
            try {
                $response = $this->client->updateSessionWebhook($connectionId, $webhookUrl);

                Log::info('[WhatsApp:D-API] webhook registrado pós-conexão', [
                    'tenant_id' => $tenant->id,
                    'connection_id' => $connectionId,
                    'webhook_url' => $webhookUrl,
                    'response' => $response,
                ]);
            } catch (\Throwable $e) {
                Log::error('[WhatsApp:D-API] falha ao registrar webhook pós-conexão', [
                    'tenant_id' => $tenant->id,
                    'connection_id' => $connectionId,
                    'webhook_url' => $webhookUrl,
                    'error' => $e->getMessage(),
                ]);
            }
        } else {
            Log::warning('[WhatsApp:D-API] webhook não registrado — URL pública indisponível', [
                'tenant_id' => $tenant->id,
                'connection_id' => $connectionId,
                'webhook_base_url_config' => config('whatsapp.d_api.webhook_base_url'),
                'app_url' => config('app.url'),
            ]);
        }

        WhatsAppConnectionOwnership::clearConnectNonce($tenant);

        return new ConnectionResultDTO(
            settings: [
                'provider' => $this->key(),
                'connection_id' => $connectionId,
                'session_id' => $connectionId,
                'phone_number' => $phoneNumber !== null ? (string) $phoneNumber : null,
                'status' => $status,
                'connected_at' => now()->toIso8601String(),
            ],
            connected: true,
            message: 'WhatsApp conectado via D-API.',
        );
    }

    public function status(Tenant $tenant): ConnectionStatusDTO
    {
        if (! $tenant->isWhatsappConnected()) {
            return new ConnectionStatusDTO(connected: false, message: 'WhatsApp não conectado.');
        }

        $connectionId = (string) (
            $tenant->whatsappSetting('connection_id')
            ?: $tenant->whatsappSetting('session_id')
        );

        try {
            $response = $this->client->getSession($connectionId);
            $ok = ($response['success'] ?? true) !== false && ! isset($response['error']);

            return new ConnectionStatusDTO(
                connected: $ok,
                message: $ok ? 'Conexão ativa.' : (string) ($response['error'] ?? 'Sessão inacessível.'),
                details: [
                    'connection_id' => $connectionId,
                    'phone_number' => $tenant->whatsappSetting('phone_number'),
                ],
            );
        } catch (\Throwable $e) {
            return new ConnectionStatusDTO(connected: false, message: $e->getMessage());
        }
    }

    /**
     * URL pública do webhook (D-API precisa alcançar da nuvem).
     */
    private function publicWebhookUrl(Tenant $tenant): ?string
    {
        $base = rtrim((string) config('whatsapp.d_api.webhook_base_url', ''), '/');

        $url = $base !== ''
            ? $base.'/api/webhooks/whatsapp/'.$tenant->uuid
            : url('/api/webhooks/whatsapp/'.$tenant->uuid);

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        if ($host === '' || in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            Log::debug('[WhatsApp:D-API] webhook omitido (host local)', [
                'url' => $url,
                'host' => $host,
            ]);

            return null;
        }

        if (str_ends_with($host, '.local') || str_ends_with($host, '.internal')) {
            Log::debug('[WhatsApp:D-API] webhook omitido (host privado)', [
                'url' => $url,
                'host' => $host,
            ]);

            return null;
        }

        return $url;
    }
}
