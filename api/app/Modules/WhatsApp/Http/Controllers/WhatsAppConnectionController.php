<?php

namespace App\Modules\WhatsApp\Http\Controllers;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Audit\Enums\AuditAction;
use App\Modules\Audit\Services\AuditLogService;
use App\Modules\Shared\Http\Controllers\ApiController;
use App\Modules\Tenant\Support\Facades\TenantContext;
use App\Modules\WhatsApp\Contracts\WhatsAppProvider;
use App\Modules\WhatsApp\Http\Requests\ConnectWhatsAppRequest;
use App\Modules\WhatsApp\Models\WhatsAppWebhookLog;
use Illuminate\Http\JsonResponse;

class WhatsAppConnectionController extends ApiController
{
    public function __construct(
        private readonly WhatsAppProvider $provider,
        private readonly AuditLogService $audit,
    ) {}

    public function show(): JsonResponse
    {
        $this->authorizePermission(Permission::WHATSAPP_CONFIG_READ);

        $tenant = TenantContext::tenant();
        $settings = $tenant->whatsappSettings();
        $status = $this->provider->getConnectionStatus($tenant);

        return $this->success([
            'provider' => $this->provider->key(),
            'connected' => $status->connected && $tenant->isWhatsappConnected(),
            'status_message' => $status->message,
            'settings' => $this->publicSettings($settings),
            'webhook_url' => url('/api/webhooks/whatsapp/'.$tenant->uuid),
        ]);
    }

    public function connect(ConnectWhatsAppRequest $request): JsonResponse
    {
        $this->authorizePermission(Permission::WHATSAPP_CONFIG_CREATE);

        $tenant = TenantContext::tenant();
        $result = $this->provider->createConnection($tenant, $request->validated());

        if (! $result->connected) {
            return $this->success(null, $result->message ?? 'Falha ao conectar WhatsApp.', 400);
        }

        $tenant->mergeWhatsappSettings($result->settings);
        $tenant->mergeOnboardingSettings([
            'whatsapp_completed' => true,
        ]);

        if ($user = $request->user()) {
            $this->audit->record($user, AuditAction::WhatsAppConnected, $tenant);
        }

        return $this->success([
            'provider' => $this->provider->key(),
            'connected' => true,
            'settings' => $this->publicSettings($tenant->fresh()->whatsappSettings()),
            'webhook_url' => url('/api/webhooks/whatsapp/'.$tenant->uuid),
        ], $result->message ?? 'WhatsApp conectado.');
    }

    public function disconnect(): JsonResponse
    {
        $this->authorizePermission(Permission::WHATSAPP_CONFIG_DELETE);

        $tenant = TenantContext::tenant();
        $this->provider->disconnectConnection($tenant);
        $tenant->clearWhatsappSettings();
        $tenant->mergeOnboardingSettings([
            'whatsapp_completed' => false,
        ]);

        if ($user = request()->user()) {
            $this->audit->record($user, AuditAction::WhatsAppDisconnected, $tenant);
        }

        return $this->success(null, 'WhatsApp desconectado.');
    }

    public function test(): JsonResponse
    {
        $this->authorizePermission(Permission::WHATSAPP_CONFIG_UPDATE);

        $tenant = TenantContext::tenant();
        $status = $this->provider->getConnectionStatus($tenant);

        if ($status->connected) {
            $tenant->mergeWhatsappSettings([
                'status' => 'connected',
                'last_checked_at' => now()->toIso8601String(),
            ]);

            return $this->success(null, $status->message ?? 'Conexão OK.');
        }

        return $this->success(null, $status->message ?? 'Falha na conexão.', 400);
    }

    public function webhookLogs(): JsonResponse
    {
        $this->authorizePermission(Permission::WHATSAPP_CONFIG_READ);

        $logs = WhatsAppWebhookLog::query()
            ->where('tenant_id', TenantContext::tenantId())
            ->latest()
            ->limit(100)
            ->get();

        // SEC-14: resumo sem headers/payload brutos por default.
        return $this->success($logs->map(fn (WhatsAppWebhookLog $log) => [
            'id' => $log->id,
            'method' => $log->method,
            'url' => $log->url,
            'response_status' => $log->response_status,
            'error_message' => $log->error_message,
            'duration_ms' => $log->duration_ms,
            'created_at' => $log->created_at?->toIso8601String(),
            'event_summary' => $this->summarizeWebhookPayload($log->request_payload),
        ]));
    }

    private function authorizePermission(Permission $permission): void
    {
        abort_unless(request()->user()?->hasPermission($permission), 403);
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function publicSettings(array $settings): array
    {
        unset(
            $settings['webhook_verify_token'],
            $settings['connection_id'],
            $settings['session_id'],
            $settings['access_token'],
            $settings['api_token'],
        );

        return $settings;
    }

    /**
     * @param  mixed  $payload
     * @return array<string, mixed>
     */
    private function summarizeWebhookPayload(mixed $payload): array
    {
        if (! is_array($payload)) {
            return [];
        }

        return array_filter([
            'object' => $payload['object'] ?? null,
            'event' => $payload['event'] ?? $payload['type'] ?? null,
            'message_count' => isset($payload['messages']) && is_array($payload['messages'])
                ? count($payload['messages'])
                : null,
            'status_count' => isset($payload['statuses']) && is_array($payload['statuses'])
                ? count($payload['statuses'])
                : null,
        ], fn (mixed $v): bool => $v !== null);
    }
}
