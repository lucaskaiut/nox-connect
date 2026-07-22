<?php

namespace App\Modules\WhatsApp\Http\Resources;

use App\Modules\WhatsApp\Models\WhatsAppConversation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WhatsAppConversation
 */
class ConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'contact' => [
                'id' => $this->contact->id,
                'wa_id' => $this->contact->wa_id,
                'profile_name' => $this->contact->profile_name,
                'display_name' => $this->contact->display_name,
            ],
            'status' => $this->status,
            'last_message_preview' => $this->last_message_preview,
            'last_message_at' => $this->last_message_at?->toIso8601String(),
            'is_unread' => $this->is_unread,
            'current_assignment' => $this->whenLoaded('currentAssignment', function () {
                $assignment = $this->currentAssignment;

                if (! $assignment) {
                    return null;
                }

                return [
                    'id' => $assignment->id,
                    'user' => $assignment->relationLoaded('user') ? [
                        'id' => $assignment->user->getKey(),
                        'name' => $assignment->user->name,
                    ] : null,
                    'assigned_at' => $assignment->assigned_at?->toIso8601String(),
                ];
            }),
            'tags' => TagResource::collection($this->whenLoaded('tags')),
            'current_stage' => $this->whenLoaded('currentStage', function () {
                if (! $this->currentStage) {
                    return null;
                }

                return [
                    'id' => $this->currentStage->id,
                    'name' => $this->currentStage->name,
                    'color' => $this->currentStage->color,
                ];
            }),
            'message_count' => $this->whenCounted('messages'),
            'messages' => MessageResource::collection($this->whenLoaded('messages')),
            'notes' => $this->whenLoaded('notes', fn () =>
                $this->notes->map(fn ($note) => [
                    'id' => $note->id,
                    'content' => $note->content,
                    'user' => $note->relationLoaded('user') ? [
                        'id' => $note->user->getKey(),
                        'name' => $note->user->name,
                    ] : null,
                    'created_at' => $note->created_at?->toIso8601String(),
                ])
            ),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
