<?php

namespace App\Modules\WhatsApp\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConversationTransferred implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly int $tenantId,
        public readonly int $conversationId,
        public readonly int $fromUserId,
        public readonly array $toUser,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('private-tenant.' . $this->tenantId),
            new Channel('private-conversation.' . $this->conversationId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'conversation.transferred';
    }

    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversationId,
            'from_user_id' => $this->fromUserId,
            'to_user' => $this->toUser,
        ];
    }
}
