<?php

namespace App\Modules\WhatsApp\Services;

use App\Modules\WhatsApp\Events\KanbanCardMoved;
use App\Modules\WhatsApp\Models\KanbanStage;
use App\Modules\WhatsApp\Models\WhatsAppConversation;
use App\Modules\WhatsApp\Models\WhatsAppConversationStageMove;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class KanbanService
{
    public function listStages(): Collection
    {
        return KanbanStage::query()->orderBy('sort_order')->get();
    }

    public function createStage(array $data): KanbanStage
    {
        return KanbanStage::query()->create($data);
    }

    public function updateStage(KanbanStage $stage, array $data): KanbanStage
    {
        $stage->fill($data)->save();

        return $stage->fresh();
    }

    public function deleteStage(KanbanStage $stage): void
    {
        $stage->delete();
    }

    public function moveConversation(WhatsAppConversation $conversation, ?int $stageId, string $userId): void
    {
        if ($stageId !== null && KanbanStage::query()->find($stageId) === null) {
            throw (new ModelNotFoundException)->setModel(KanbanStage::class, [$stageId]);
        }

        $fromStageId = $conversation->current_stage_id;

        WhatsAppConversationStageMove::query()->create([
            'conversation_id' => $conversation->id,
            'stage_id' => $stageId,
            'user_id' => $userId,
            'moved_at' => now(),
        ]);

        $conversation->update(['current_stage_id' => $stageId]);

        $contactName = $conversation->contact?->display_name
            ?? $conversation->contact?->profile_name
            ?? 'Contato';

        broadcast(new KanbanCardMoved(
            $conversation->tenantUuid(),
            $conversation->id,
            $fromStageId,
            $stageId,
            $contactName,
        ));
    }

    public function getStageHistory(WhatsAppConversation $conversation): Collection
    {
        return $conversation->stageMoves()->with(['stage', 'user'])->get();
    }

    /**
     * @deprecated Prefer listConversationsForStage() with cursor pagination.
     *
     * @return list<array{stage: KanbanStage, conversations: Collection<int, WhatsAppConversation>}>
     */
    public function getConversationsByStage(): array
    {
        $stages = $this->listStages();
        $result = [];

        foreach ($stages as $stage) {
            $conversations = WhatsAppConversation::query()
                ->with(['contact', 'lastMessage', 'currentAssignment.user', 'tags', 'currentStage'])
                ->where('current_stage_id', $stage->id)
                ->orderByDesc('last_message_at')
                ->orderByDesc('id')
                ->limit(20)
                ->get();

            $result[] = [
                'stage' => $stage,
                'conversations' => $conversations,
            ];
        }

        return $result;
    }

    public function listConversationsForStage(KanbanStage $stage, int $perPage = 20): CursorPaginator
    {
        return WhatsAppConversation::query()
            ->with(['contact', 'lastMessage', 'currentAssignment.user', 'tags', 'currentStage'])
            ->where('current_stage_id', $stage->id)
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->cursorPaginate($perPage);
    }

    public function seedDefaultStages(): void
    {
        $defaults = [
            ['name' => 'Novo Lead', 'color' => '#6B7280', 'sort_order' => 0],
            ['name' => 'Contato Realizado', 'color' => '#3B82F6', 'sort_order' => 1],
            ['name' => 'Proposta Enviada', 'color' => '#F59E0B', 'sort_order' => 2],
            ['name' => 'Negociação', 'color' => '#8B5CF6', 'sort_order' => 3],
            ['name' => 'Fechado', 'color' => '#10B981', 'sort_order' => 4],
            ['name' => 'Perdido', 'color' => '#EF4444', 'sort_order' => 5],
        ];

        foreach ($defaults as $data) {
            KanbanStage::query()->firstOrCreate(
                ['tenant_id' => TenantContext::tenantId(), 'name' => $data['name']],
                $data
            );
        }
    }
}
