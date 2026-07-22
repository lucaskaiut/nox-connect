<?php

namespace App\Modules\WhatsApp\Http\Controllers;

use App\Modules\Shared\Http\Controllers\ApiController;
use App\Modules\WhatsApp\Http\Requests\MoveStageRequest;
use App\Modules\WhatsApp\Http\Requests\StoreKanbanStageRequest;
use App\Modules\WhatsApp\Http\Requests\UpdateKanbanStageRequest;
use App\Modules\WhatsApp\Http\Resources\KanbanStageResource;
use App\Modules\WhatsApp\Models\KanbanStage;
use App\Modules\WhatsApp\Models\WhatsAppConversation;
use App\Modules\WhatsApp\Services\KanbanService;
use Illuminate\Http\JsonResponse;

class KanbanController extends ApiController
{
    public function __construct(
        private readonly KanbanService $service,
    ) {}

    public function board(): JsonResponse
    {
        return $this->success($this->service->getConversationsByStage());
    }

    public function stages(): JsonResponse
    {
        return $this->success(KanbanStageResource::collection($this->service->listStages()));
    }

    public function storeStage(StoreKanbanStageRequest $request): JsonResponse
    {
        $this->authorize('create', KanbanStage::class);

        return $this->created(
            KanbanStageResource::make($this->service->createStage($request->validated())),
            'Etapa criada com sucesso.',
        );
    }

    public function updateStage(UpdateKanbanStageRequest $request, KanbanStage $stage): JsonResponse
    {
        $this->authorize('update', $stage);

        return $this->success(
            KanbanStageResource::make($this->service->updateStage($stage, $request->validated())),
            'Etapa atualizada com sucesso.',
        );
    }

    public function deleteStage(KanbanStage $stage): JsonResponse
    {
        $this->authorize('delete', $stage);

        $this->service->deleteStage($stage);

        return $this->success(null, 'Etapa removida com sucesso.');
    }

    public function moveConversation(MoveStageRequest $request, WhatsAppConversation $conversation): JsonResponse
    {
        $this->authorize('update', $conversation);

        $this->service->moveConversation(
            $conversation,
            $request->validated('stage_id'),
            $request->user()->getKey(),
        );

        return $this->success(null, 'Card movido com sucesso.');
    }

    public function conversationHistory(WhatsAppConversation $conversation): JsonResponse
    {
        return $this->success($this->service->getStageHistory($conversation));
    }

    public function seedDefaults(): JsonResponse
    {
        $this->authorize('create', KanbanStage::class);

        $this->service->seedDefaultStages();

        return $this->success(null, 'Etapas padrão criadas com sucesso.');
    }
}
