<?php

namespace App\Modules\WhatsApp\Http\Controllers;

use App\Modules\Shared\Http\Controllers\ApiController;
use App\Modules\WhatsApp\Http\Requests\StoreWhatsAppConfigRequest;
use App\Modules\WhatsApp\Http\Requests\UpdateWhatsAppConfigRequest;
use App\Modules\WhatsApp\Http\Resources\WhatsAppConfigResource;
use App\Modules\WhatsApp\Models\WhatsAppConfig;
use App\Modules\WhatsApp\Models\WhatsAppWebhookLog;
use App\Modules\WhatsApp\Services\WhatsAppCloudApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class WhatsAppConfigController extends ApiController
{
    public function __construct(
        private readonly WhatsAppCloudApi $cloudApi,
    ) {}

    public function index(): JsonResponse
    {
        $this->authorize('viewAny', WhatsAppConfig::class);

        return $this->success(WhatsAppConfigResource::collection(
            WhatsAppConfig::query()->latest()->get()
        ));
    }

    public function store(StoreWhatsAppConfigRequest $request): JsonResponse
    {
        $this->authorize('create', WhatsAppConfig::class);

        $data = $request->validated();

        if (blank($data['verify_token'] ?? null)) {
            $data['verify_token'] = Str::random(40);
        }

        $config = WhatsAppConfig::query()->create($data);

        return $this->created(
            WhatsAppConfigResource::make($config),
            'Configuração do WhatsApp criada com sucesso.',
        );
    }

    public function show(WhatsAppConfig $config): JsonResponse
    {
        $this->authorize('view', $config);

        return $this->success(WhatsAppConfigResource::make($config));
    }

    public function update(UpdateWhatsAppConfigRequest $request, WhatsAppConfig $config): JsonResponse
    {
        $this->authorize('update', $config);

        $data = $request->validated();

        if ($request->has('verify_token') && blank($data['verify_token'])) {
            $data['verify_token'] = Str::random(40);
        }

        $config->fill($data)->save();

        return $this->success(
            WhatsAppConfigResource::make($config->fresh()),
            'Configuração atualizada com sucesso.',
        );
    }

    public function destroy(WhatsAppConfig $config): JsonResponse
    {
        $this->authorize('delete', $config);

        $config->delete();

        return $this->success(null, 'Configuração removida com sucesso.');
    }

    public function testConnection(WhatsAppConfig $config): JsonResponse
    {
        $this->authorize('update', $config);

        try {
            $success = $this->cloudApi->verifyConnection($config);

            if ($success) {
                $config->update(['last_connected_at' => now()]);

                return $this->success(null, 'Conexão realizada com sucesso.');
            }

            return $this->success(null, 'Falha ao conectar. Verifique as credenciais.', 400);
        } catch (\Throwable $e) {
            return $this->success(null, "Erro ao testar conexão: {$e->getMessage()}", 400);
        }
    }

    public function toggle(WhatsAppConfig $config): JsonResponse
    {
        $this->authorize('update', $config);

        $config->update(['is_active' => ! $config->is_active]);

        return $this->success(
            WhatsAppConfigResource::make($config->fresh()),
            $config->is_active ? 'Conexão ativada com sucesso.' : 'Conexão desativada com sucesso.',
        );
    }

    public function webhookLogs(WhatsAppConfig $config): JsonResponse
    {
        $this->authorize('view', $config);

        $logs = WhatsAppWebhookLog::query()
            ->where('whatsapp_config_id', $config->id)
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
}
