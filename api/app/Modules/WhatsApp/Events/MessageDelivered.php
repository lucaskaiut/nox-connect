<?php

namespace App\Modules\WhatsApp\Events;

use Illuminate\Broadcasting\PrivateChannel;
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
        public readonly string $externalMessageId,
        public readonly string $status,
        public readonly string $deliveredAt,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('conversation.' . $this->conversationId),
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
            'external_message_id' => $this->externalMessageId,
            'status' => $this->status,
            'delivered_at' => $this->deliveredAt,
        ];
    }
}
