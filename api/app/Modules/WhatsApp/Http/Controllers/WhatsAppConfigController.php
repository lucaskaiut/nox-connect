<?php

namespace App\Modules\WhatsApp\Http\Controllers;

use App\Modules\Shared\Http\Controllers\ApiController;
use App\Modules\WhatsApp\Http\Requests\StoreWhatsAppConfigRequest;
use App\Modules\WhatsApp\Http\Requests\UpdateWhatsAppConfigRequest;
use App\Modules\WhatsApp\Http\Resources\WhatsAppConfigResource;
use App\Modules\WhatsApp\Models\WhatsAppConfig;
use App\Modules\WhatsApp\Services\WhatsAppCloudApi;
use Illuminate\Http\JsonResponse;

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

        $config = WhatsAppConfig::query()->create($request->validated());

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

        $config->fill($request->validated())->save();

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
}
