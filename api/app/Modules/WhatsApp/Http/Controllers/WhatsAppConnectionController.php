<?php

namespace App\Modules\WhatsApp\Http\Controllers;

use App\Modules\ACL\Enums\Permission;
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

        return $this->success($logs->map(fn (WhatsAppWebhookLog $log) => [
            'id' => $log->id,
            'method' => $log->method,
            'url' => $log->url,
            'request_headers' => $log->request_headers,
            'request_payload' => $log->request_payload,
            'response_status' => $log->response_status,
            'response_body' => $log->response_body,
            'error_message' => $log->error_message,
            'duration_ms' => $log->duration_ms,
            'created_at' => $log->created_at?->toIso8601String(),
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
        unset($settings['webhook_verify_token']);

        return $settings;
    }
}
