<?php

namespace App\Modules\WhatsApp\Http\Controllers;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Shared\Http\Controllers\ApiController;
use App\Modules\WhatsApp\Http\Requests\DeleteMessageTemplateRequest;
use App\Modules\WhatsApp\Http\Requests\StoreMessageTemplateRequest;
use App\Modules\WhatsApp\Http\Requests\UpdateMessageTemplateRequest;
use App\Modules\WhatsApp\Http\Resources\MessageTemplateResource;
use App\Modules\WhatsApp\Services\MessageTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MessageTemplateController extends ApiController
{
    public function __construct(
        private readonly MessageTemplateService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorizePermission(Permission::WHATSAPP_TEMPLATE_READ);

        $response = $this->service->list($request->only(['fields', 'limit', 'after', 'before']));
        $data = $response['data'] ?? [];

        return $this->success([
            'data' => MessageTemplateResource::collection(collect($data)),
            'paging' => $response['paging'] ?? null,
        ]);
    }

    public function store(StoreMessageTemplateRequest $request): JsonResponse
    {
        $this->authorizePermission(Permission::WHATSAPP_TEMPLATE_CREATE);

        $response = $this->service->create($request->validated());

        if (! empty($response['error'])) {
            $detail = $response['error']['error_user_msg']
                ?? $response['error']['error_user_title']
                ?? '';
            $message = $response['error']['message'] ?? 'Erro ao criar template.';
            if ($detail) {
                $message .= ' — ' . $detail;
            }

            return $this->success($response['error'], $message, 400);
        }

        return $this->created([
            'id' => $response['id'] ?? null,
            'status' => $response['status'] ?? null,
            'category' => $response['category'] ?? null,
        ], 'Template criado com sucesso.');
    }

    public function show(Request $request, string $templateId): JsonResponse
    {
        $this->authorizePermission(Permission::WHATSAPP_TEMPLATE_READ);

        $response = $this->service->get($templateId, $request->query('fields'));

        if (! empty($response['error'])) {
            $status = ($response['error']['code'] ?? null) == 803 ? 404 : 400;

            return $this->success(null, $response['error']['message'] ?? 'Erro ao obter template.', $status);
        }

        return $this->success(MessageTemplateResource::make($response));
    }

    public function update(UpdateMessageTemplateRequest $request, string $templateId): JsonResponse
    {
        $this->authorizePermission(Permission::WHATSAPP_TEMPLATE_UPDATE);

        $response = $this->service->update($templateId, $request->validated());

        if (! empty($response['error'])) {
            return $this->success(null, $response['error']['message'] ?? 'Erro ao atualizar template.', 400);
        }

        return $this->success($response, 'Template atualizado com sucesso.');
    }

    public function destroy(DeleteMessageTemplateRequest $request): JsonResponse
    {
        $this->authorizePermission(Permission::WHATSAPP_TEMPLATE_DELETE);

        $response = $this->service->delete($request->validated());

        if (! empty($response['error'])) {
            return $this->success(null, $response['error']['message'] ?? 'Erro ao excluir template(s).', 400);
        }

        return $this->success($response, 'Template(s) removido(s) com sucesso.');
    }

    private function authorizePermission(Permission $permission): void
    {
        abort_unless(request()->user()?->hasPermission($permission), 403);
    }
}
