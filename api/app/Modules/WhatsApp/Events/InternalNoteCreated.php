<?php

namespace App\Modules\WhatsApp\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InternalNoteCreated implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly string $tenantId,
        public readonly int $conversationId,
        public readonly array $note,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('private-conversation.' . $this->conversationId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'internal.note.created';
    }

    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversationId,
            'note' => $this->note,
        ];
    }
}
