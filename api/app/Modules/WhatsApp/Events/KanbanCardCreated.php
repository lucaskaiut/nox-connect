<?php

namespace App\Modules\WhatsApp\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class KanbanCardCreated implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly int $tenantId,
        public readonly int $conversationId,
        public readonly int $stageId,
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
        return 'kanban.card.created';
    }

    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversationId,
            'stage_id' => $this->stageId,
            'contact_name' => $this->contactName,
        ];
    }
}
