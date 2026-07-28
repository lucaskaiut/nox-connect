<?php

namespace App\Modules\WhatsApp\Services;

use App\Modules\Tenant\Models\Tenant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Garante que connection_id/session_id/channel_id não possam ser reivindicados
 * cross-tenant (SEC-03 / SEC-04).
 *
 * Sem assinatura do provider, a defesa é: (1) binding initialize→complete via
 * nonce de curta duração; (2) rejeitar IDs já vinculados a outro tenant.
 */
final class WhatsAppConnectionOwnership
{
    private const PENDING_TTL_MINUTES = 30;

    public static function beginConnect(Tenant $tenant): string
    {
        $nonce = Str::random(48);
        Cache::put(self::pendingKey($tenant), $nonce, now()->addMinutes(self::PENDING_TTL_MINUTES));

        return $nonce;
    }

    public static function assertConnectNonce(Tenant $tenant, ?string $nonce): void
    {
        $expected = Cache::get(self::pendingKey($tenant));

        if (! is_string($expected) || $expected === '' || ! is_string($nonce) || $nonce === '') {
            throw ValidationException::withMessages([
                'connection_nonce' => ['Inicie a conexão antes de concluir (nonce ausente ou expirado).'],
            ]);
        }

        if (! hash_equals($expected, $nonce)) {
            throw ValidationException::withMessages([
                'connection_nonce' => ['Nonce de conexão inválido.'],
            ]);
        }
    }

    public static function clearConnectNonce(Tenant $tenant): void
    {
        Cache::forget(self::pendingKey($tenant));
    }

    /**
     * Rejeita claim de ID externo já associado a outro tenant.
     *
     * @throws ValidationException
     */
    public static function assertExternalIdAvailable(Tenant $tenant, string $externalId, string $field = 'connection_id'): void
    {
        if ($externalId === '') {
            return;
        }

        $own = (string) ($tenant->whatsappSetting('connection_id')
            ?: $tenant->whatsappSetting('session_id')
            ?: $tenant->whatsappSetting('channel_id')
            ?: '');

        if ($own !== '' && hash_equals($own, $externalId)) {
            return;
        }

        $claimed = Tenant::query()
            ->whereKeyNot($tenant->getKey())
            ->get(['id', 'settings'])
            ->contains(function (Tenant $other) use ($externalId): bool {
                $wa = $other->whatsappSettings();

                foreach (['connection_id', 'session_id', 'channel_id'] as $key) {
                    $value = (string) ($wa[$key] ?? '');
                    if ($value !== '' && hash_equals($value, $externalId)) {
                        return true;
                    }
                }

                return false;
            });

        if ($claimed) {
            throw ValidationException::withMessages([
                $field => ['Este identificador de conexão já está vinculado a outra empresa.'],
            ]);
        }
    }

    /**
     * Se o webhook da sessão já aponta para outro tenant, bloqueia o takeover.
     */
    public static function assertWebhookBelongsToTenant(Tenant $tenant, ?string $existingWebhookUrl): void
    {
        if ($existingWebhookUrl === null || $existingWebhookUrl === '') {
            return;
        }

        if (! preg_match('#/webhooks/whatsapp/([0-9a-fA-F-]{36})#', $existingWebhookUrl, $matches)) {
            return;
        }

        if (! hash_equals(strtolower($tenant->uuid), strtolower($matches[1]))) {
            throw ValidationException::withMessages([
                'connection_id' => ['Esta sessão já está registrada para outro tenant.'],
            ]);
        }
    }

    private static function pendingKey(Tenant $tenant): string
    {
        return 'whatsapp:connect:pending:'.$tenant->getKey();
    }
}
