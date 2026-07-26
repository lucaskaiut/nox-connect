<?php

namespace App\Modules\WhatsApp\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConversationClosed implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly string $tenantId,
        public readonly int $conversationId,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('tenant.' . $this->tenantId),
            new PrivateChannel('conversation.' . $this->conversationId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'conversation.closed';
    }

    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversationId,
        ];
    }
}
