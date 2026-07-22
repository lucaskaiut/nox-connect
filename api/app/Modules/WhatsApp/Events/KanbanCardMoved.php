<?php

namespace App\Modules\WhatsApp\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class KanbanCardMoved implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly int $tenantId,
        public readonly int $conversationId,
        public readonly ?int $fromStageId,
        public readonly ?int $toStageId,
        public readonly string $contactName,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('private-tenant.' . $this->tenantId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'kanban.card.moved';
    }

    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversationId,
            'from_stage_id' => $this->fromStageId,
            'to_stage_id' => $this->toStageId,
            'contact_name' => $this->contactName,
        ];
    }
}
