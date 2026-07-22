<?php

namespace App\Modules\WhatsApp\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageDelivered implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly string $tenantId,
        public readonly int $conversationId,
        public readonly string $waMessageId,
        public readonly string $status,
        public readonly string $deliveredAt,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('private-conversation.' . $this->conversationId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.delivered';
    }

    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversationId,
            'wa_message_id' => $this->waMessageId,
            'status' => $this->status,
            'delivered_at' => $this->deliveredAt,
        ];
    }
}
