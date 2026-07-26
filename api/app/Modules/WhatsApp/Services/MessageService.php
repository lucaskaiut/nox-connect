<?php

namespace App\Modules\WhatsApp\Services;

use App\Modules\Tenant\Models\Tenant;
use App\Modules\WhatsApp\Contracts\WhatsAppProvider;
use App\Modules\WhatsApp\DTOs\SendAudioDTO;
use App\Modules\WhatsApp\DTOs\SendDocumentDTO;
use App\Modules\WhatsApp\DTOs\SendImageDTO;
use App\Modules\WhatsApp\DTOs\SendTextMessageDTO;
use App\Modules\WhatsApp\DTOs\SendVideoDTO;
use App\Modules\WhatsApp\Enums\MessageDirection;
use App\Modules\WhatsApp\Enums\MessageStatus;
use App\Modules\WhatsApp\Events\MessageSent;
use App\Modules\WhatsApp\Models\WhatsAppConversation;
use App\Modules\WhatsApp\Models\WhatsAppMessage;

class MessageService
{
    public function __construct(
        private readonly WhatsAppProvider $provider,
    ) {}

    public function sendText(WhatsAppConversation $conversation, string $text): WhatsAppMessage
    {
        $tenant = $this->resolveTenant($conversation);
        $contact = $conversation->contact()->firstOrFail();

        $assignment = $conversation->currentAssignment()->with('user')->first();
        $senderName = $assignment?->user?->name;

        $body = $senderName ? "*{$senderName}*\n\n{$text}" : $text;

        $result = $this->provider->sendText(new SendTextMessageDTO(
            tenant: $tenant,
            to: $contact->external_contact_id,
            body: $body,
        ));

        $status = $result->success ? MessageStatus::Sent->value : MessageStatus::Failed->value;
        $metadata = $senderName ? ['sender_name' => $senderName] : null;

        if ($result->error !== null) {
            $metadata = array_merge($metadata ?? [], $result->error->toMetadata());
        }

        $message = WhatsAppMessage::query()->create([
            'conversation_id' => $conversation->id,
            'direction' => MessageDirection::Outbound->value,
            'message_type' => 'text',
            'content' => $text,
            'status' => $status,
            'external_message_id' => $result->externalMessageId,
            'metadata' => $metadata,
        ]);

        $conversation->update([
            'last_message_preview' => $result->error
                ? '❌ ' . mb_strimwidth($result->error->message ?? 'Erro ao enviar', 0, 100, '...')
                : mb_strimwidth($text, 0, 120, '...'),
            'last_message_at' => now(),
            'is_unread' => false,
        ]);

        if ($status === MessageStatus::Sent->value) {
            broadcast(new MessageSent(
                $conversation->tenantUuid(),
                $conversation->id,
                $message->fresh()->toArray(),
            ));
        }

        return $message;
    }

    public function sendMedia(WhatsAppConversation $conversation, string $filePath, string $mimeType, ?string $caption = null): ?WhatsAppMessage
    {
        $tenant = $this->resolveTenant($conversation);
        $contact = $conversation->contact()->firstOrFail();

        $assignment = $conversation->currentAssignment()->with('user')->first();
        $senderName = $assignment?->user?->name;

        $mediaCaption = $caption && $senderName ? "*{$senderName}*\n\n{$caption}" : $caption;

        $mediaId = $this->provider->uploadMedia($tenant, $filePath, $mimeType);

        if (! $mediaId) {
            return null;
        }

        $type = match (true) {
            str_starts_with($mimeType, 'image/') => 'image',
            str_starts_with($mimeType, 'video/') => 'video',
            str_starts_with($mimeType, 'audio/') => 'audio',
            default => 'document',
        };

        $to = $contact->external_contact_id;

        $result = match ($type) {
            'image' => $this->provider->sendImage(new SendImageDTO($tenant, $to, $mediaId, $mediaCaption)),
            'video' => $this->provider->sendVideo(new SendVideoDTO($tenant, $to, $mediaId, $mediaCaption)),
            'audio' => $this->provider->sendAudio(new SendAudioDTO($tenant, $to, $mediaId)),
            'document' => $this->provider->sendDocument(new SendDocumentDTO($tenant, $to, $mediaId, null, $mediaCaption)),
        };

        $preview = match ($type) {
            'image' => '📷 Imagem',
            'video' => '🎬 Vídeo',
            'audio' => '🎵 Áudio',
            'document' => '📄 Documento',
        };

        $metadata = $senderName ? ['sender_name' => $senderName] : null;

        if ($result->error !== null) {
            $metadata = array_merge($metadata ?? [], $result->error->toMetadata());
        }

        $message = WhatsAppMessage::query()->create([
            'conversation_id' => $conversation->id,
            'direction' => MessageDirection::Outbound->value,
            'message_type' => $type,
            'content' => $caption,
            'media' => ['id' => $mediaId, 'mime_type' => $mimeType],
            'metadata' => $metadata,
            'status' => $result->success ? MessageStatus::Sent->value : MessageStatus::Failed->value,
            'external_message_id' => $result->externalMessageId,
        ]);

        $conversation->update([
            'last_message_preview' => $preview,
            'last_message_at' => now(),
            'is_unread' => false,
        ]);

        return $message;
    }

    private function resolveTenant(WhatsAppConversation $conversation): Tenant
    {
        if ($conversation->relationLoaded('tenant') && $conversation->tenant) {
            return $conversation->tenant;
        }

        return Tenant::query()->findOrFail($conversation->tenant_id);
    }
}
