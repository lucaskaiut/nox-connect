<?php

namespace App\Modules\WhatsApp\Services;

use App\Modules\WhatsApp\Enums\ConversationStatus;
use App\Modules\WhatsApp\Enums\MessageDirection;
use App\Modules\WhatsApp\Enums\MessageStatus;
use App\Modules\WhatsApp\Events\ConversationAssigned;
use App\Modules\WhatsApp\Events\ConversationClosed;
use App\Modules\WhatsApp\Events\ConversationTransferred;
use App\Modules\WhatsApp\Models\WhatsAppConfig;
use App\Modules\WhatsApp\Models\WhatsAppConversation;
use App\Modules\WhatsApp\Models\WhatsAppConversationAssignment;
use App\Modules\WhatsApp\Models\WhatsAppMessage;
use App\Modules\User\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ConversationService
{
    public function list(array $filters = []): LengthAwarePaginator
    {
        $query = WhatsAppConversation::query()
            ->with(['contact', 'lastMessage', 'currentAssignment.user', 'tags', 'currentStage'])
            ->latest('last_message_at');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['unassigned']) && $filters['unassigned']) {
            $query->whereDoesntHave('currentAssignment');
        }

        if (! empty($filters['assigned_to'])) {
            $query->whereHas('currentAssignment', function ($q) use ($filters): void {
                $q->where('user_id', $filters['assigned_to']);
            });
        }

        if (! empty($filters['tag_id'])) {
            $query->whereHas('tags', function ($q) use ($filters): void {
                $q->where('whatsapp_tags.id', $filters['tag_id']);
            });
        }

        if (! empty($filters['stage_id'])) {
            $query->whereHas('currentStage', function ($q) use ($filters): void {
                $q->where('stage_id', $filters['stage_id']);
            });
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search): void {
                $q->whereHas('contact', function ($q) use ($search): void {
                    $q->where('profile_name', 'like', "%{$search}%")
                        ->orWhere('display_name', 'like', "%{$search}%")
                        ->orWhere('wa_id', 'like', "%{$search}%");
                });
            });
        }

        return $query->paginate($filters['per_page'] ?? 20);
    }

    public function find(int $id): WhatsAppConversation
    {
        return WhatsAppConversation::query()
            ->with(['contact', 'messages', 'notes.user', 'tags', 'currentAssignment.user', 'currentStage'])
            ->findOrFail($id);
    }

    public function markAsRead(WhatsAppConversation $conversation): void
    {
        $conversation->update(['is_unread' => false]);
    }

    public function assign(WhatsAppConversation $conversation, string $userId): void
    {
        DB::transaction(function () use ($conversation, $userId): void {
            WhatsAppConversationAssignment::query()
                ->where('conversation_id', $conversation->id)
                ->whereNull('unassigned_at')
                ->update(['unassigned_at' => now()]);

            WhatsAppConversationAssignment::query()->create([
                'conversation_id' => $conversation->id,
                'user_id' => $userId,
                'assigned_at' => now(),
            ]);
        });

        $user = User::query()->where('uuid', $userId)->first();

        broadcast(new ConversationAssigned(
            $conversation->tenant_id,
            $conversation->id,
            $user ? ['id' => $user->uuid, 'name' => $user->name] : ['id' => $userId, 'name' => 'Desconhecido'],
        ))->toOthers();
    }

    public function transfer(WhatsAppConversation $conversation, string $userId): void
    {
        $prevAssignment = $conversation->currentAssignment()->first();
        $fromUserId = $prevAssignment?->user_id;

        $this->assign($conversation, $userId);

        $user = User::query()->where('uuid', $userId)->first();

        broadcast(new ConversationTransferred(
            $conversation->tenant_id,
            $conversation->id,
            $fromUserId,
            $user ? ['id' => $user->uuid, 'name' => $user->name] : ['id' => $userId, 'name' => 'Desconhecido'],
        ))->toOthers();
    }

    public function removeAssignment(WhatsAppConversation $conversation): void
    {
        WhatsAppConversationAssignment::query()
            ->where('conversation_id', $conversation->id)
            ->whereNull('unassigned_at')
            ->update(['unassigned_at' => now()]);
    }

    public function close(WhatsAppConversation $conversation): void
    {
        $conversation->update(['status' => ConversationStatus::Closed->value]);

        broadcast(new ConversationClosed($conversation->tenant_id, $conversation->id))->toOthers();
    }

    public function reopen(WhatsAppConversation $conversation): void
    {
        $conversation->update(['status' => ConversationStatus::Open->value]);
    }

    public function getStats(): array
    {
        return [
            'open' => WhatsAppConversation::query()->where('status', ConversationStatus::Open->value)->count(),
            'closed' => WhatsAppConversation::query()->where('status', ConversationStatus::Closed->value)->count(),
            'unassigned' => WhatsAppConversation::query()
                ->where('status', ConversationStatus::Open->value)
                ->whereDoesntHave('currentAssignment')
                ->count(),
        ];
    }
}
