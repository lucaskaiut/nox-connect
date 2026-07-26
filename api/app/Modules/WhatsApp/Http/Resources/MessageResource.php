<?php

namespace App\Modules\WhatsApp\Http\Resources;

use App\Modules\WhatsApp\Models\WhatsAppMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WhatsAppMessage
 */
class MessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'direction' => $this->direction,
            'message_type' => $this->message_type,
            'content' => $this->content,
            'media' => $this->media,
            'external_message_id' => $this->external_message_id,
            'status' => $this->status,
            'metadata' => $this->metadata,
            'sender_name' => $this->metadata['sender_name'] ?? null,
            'delivered_at' => $this->delivered_at?->toIso8601String(),
            'read_at' => $this->read_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
