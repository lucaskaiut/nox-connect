<?php

namespace App\Modules\WhatsApp\Services;

use App\Modules\WhatsApp\Enums\ConversationStatus;
use App\Modules\WhatsApp\Enums\MessageDirection;
use App\Modules\WhatsApp\Enums\MessageStatus;
use App\Modules\WhatsApp\Events\ConversationAssigned;
use App\Modules\WhatsApp\Events\ConversationClosed;
use App\Modules\WhatsApp\Events\ConversationTransferred;
use App\Modules\WhatsApp\Models\WhatsAppConversation;
use App\Modules\WhatsApp\Models\WhatsAppConversationAssignment;
use App\Modules\WhatsApp\Models\WhatsAppMessage;
use App\Modules\User\Models\User;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class ConversationService
{
    public function list(array $filters = []): CursorPaginator
    {
        $query = WhatsAppConversation::query()
            ->with(['contact', 'lastMessage', 'currentAssignment.user', 'tags', 'currentStage'])
            ->orderByDesc('last_message_at')
            ->orderByDesc('id');

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
                        ->orWhere('external_contact_id', 'like', "%{$search}%");
                });
            });
        }

        return $query->cursorPaginate($filters['per_page'] ?? 20);
    }

    public function find(int $id): WhatsAppConversation
    {
        return WhatsAppConversation::query()
            ->with(['contact', 'notes.user', 'tags', 'currentAssignment.user', 'currentStage'])
            ->withCount('messages')
            ->findOrFail($id);
    }

    public function listMessages(WhatsAppConversation $conversation, int $perPage = 50): CursorPaginator
    {
        return WhatsAppMessage::query()
            ->where('conversation_id', $conversation->id)
            ->orderByDesc('id')
            ->cursorPaginate($perPage);
    }

    public function markAsRead(WhatsAppConversation $conversation): void
    {
        $conversation->update(['is_unread' => false]);
    }

    public function assign(WhatsAppConversation $conversation, string $userId): void
    {
        $user = User::query()->where('uuid', $userId)->first();

        if ($user === null) {
            throw (new ModelNotFoundException)->setModel(User::class, [$userId]);
        }

        DB::transaction(function () use ($conversation, $user): void {
            WhatsAppConversationAssignment::query()
                ->where('conversation_id', $conversation->id)
                ->whereNull('unassigned_at')
                ->update(['unassigned_at' => now()]);

            WhatsAppConversationAssignment::query()->create([
                'conversation_id' => $conversation->id,
                'user_id' => $user->uuid,
                'assigned_at' => now(),
            ]);
        });

        broadcast(new ConversationAssigned(
            $conversation->tenantUuid(),
            $conversation->id,
            $user ? ['id' => $user->uuid, 'name' => $user->name] : ['id' => $userId, 'name' => 'Desconhecido'],
        ));
    }

    public function transfer(WhatsAppConversation $conversation, string $userId): void
    {
        $prevAssignment = $conversation->currentAssignment()->first();
        $fromUserId = $prevAssignment?->user_id;

        $this->assign($conversation, $userId);

        $user = User::query()->where('uuid', $userId)->first();

        broadcast(new ConversationTransferred(
            $conversation->tenantUuid(),
            $conversation->id,
            $fromUserId,
            $user ? ['id' => $user->uuid, 'name' => $user->name] : ['id' => $userId, 'name' => 'Desconhecido'],
        ));
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

        broadcast(new ConversationClosed($conversation->tenantUuid(), $conversation->id));
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
