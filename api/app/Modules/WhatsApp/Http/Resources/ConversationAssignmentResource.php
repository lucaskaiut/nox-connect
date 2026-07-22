<?php

namespace App\Modules\WhatsApp\Http\Resources;

use App\Modules\WhatsApp\Models\WhatsAppConversationAssignment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WhatsAppConversationAssignment
 */
class ConversationAssignmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user' => $this->relationLoaded('user') ? [
                'id' => $this->user->getKey(),
                'name' => $this->user->name,
            ] : null,
            'assigned_at' => $this->assigned_at?->toIso8601String(),
            'unassigned_at' => $this->unassigned_at?->toIso8601String(),
        ];
    }
}
