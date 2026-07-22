<?php

namespace App\Modules\WhatsApp\Services;

use App\Modules\WhatsApp\Enums\MessageDirection;
use App\Modules\WhatsApp\Enums\MessageStatus;
use App\Modules\WhatsApp\Models\WhatsAppConfig;
use App\Modules\WhatsApp\Models\WhatsAppConversation;
use App\Modules\WhatsApp\Models\WhatsAppMessage;

class MessageService
{
    public function __construct(
        private readonly WhatsAppCloudApi $cloudApi,
    ) {}

    public function sendText(WhatsAppConversation $conversation, string $text): WhatsAppMessage
    {
        $config = $conversation->config()->firstOrFail();
        $contact = $conversation->contact()->firstOrFail();

        $response = $this->cloudApi->sendText($config, $contact->wa_id, $text);
        $waMessageId = $response['messages'][0]['id'] ?? null;
        $error = $response['error'] ?? null;

        $status = $waMessageId ? MessageStatus::Sent->value : MessageStatus::Failed->value;
        $metadata = null;

        if ($error) {
            $metadata = [
                'error_code' => $error['code'] ?? null,
                'error_type' => $error['type'] ?? null,
                'error_message' => $error['message'] ?? 'Erro desconhecido da API',
                'error_subcode' => $error['error_subcode'] ?? null,
                'fbtrace_id' => $error['fbtrace_id'] ?? null,
                'error_data' => $error['error_data'] ?? null,
            ];
        }

        $message = WhatsAppMessage::query()->create([
            'conversation_id' => $conversation->id,
            'direction' => MessageDirection::Outbound->value,
            'message_type' => 'text',
            'content' => $text,
            'status' => $status,
            'wa_message_id' => $waMessageId,
            'metadata' => $metadata,
        ]);

        $conversation->update([
            'last_message_preview' => $error
                ? '❌ ' . mb_strimwidth($error['message'] ?? 'Erro ao enviar', 0, 100, '...')
                : mb_strimwidth($text, 0, 120, '...'),
            'last_message_at' => now(),
            'is_unread' => false,
        ]);

        return $message;
    }

    public function sendMedia(WhatsAppConversation $conversation, string $filePath, string $mimeType, ?string $caption = null): ?WhatsAppMessage
    {
        $config = $conversation->config()->firstOrFail();
        $contact = $conversation->contact()->firstOrFail();

        $mediaId = $this->cloudApi->uploadMedia($config, $filePath, $mimeType);

        if (! $mediaId) {
            return null;
        }

        $type = match (true) {
            str_starts_with($mimeType, 'image/') => 'image',
            str_starts_with($mimeType, 'video/') => 'video',
            str_starts_with($mimeType, 'audio/') => 'audio',
            default => 'document',
        };

        $method = match ($type) {
            'image' => 'sendImage',
            'video' => 'sendVideo',
            'audio' => 'sendAudio',
            'document' => 'sendDocument',
        };

        $response = $this->cloudApi->$method($config, $contact->wa_id, $mediaId, $caption);
        $waMessageId = $response['messages'][0]['id'] ?? null;

        $preview = match ($type) {
            'image' => '📷 Imagem',
            'video' => '🎬 Vídeo',
            'audio' => '🎵 Áudio',
            'document' => '📄 Documento',
        };

        $message = WhatsAppMessage::query()->create([
            'conversation_id' => $conversation->id,
            'direction' => MessageDirection::Outbound->value,
            'message_type' => $type,
            'content' => $caption,
            'media' => ['id' => $mediaId, 'mime_type' => $mimeType],
            'status' => $waMessageId ? MessageStatus::Sent->value : MessageStatus::Failed->value,
            'wa_message_id' => $waMessageId,
        ]);

        $conversation->update([
            'last_message_preview' => $preview,
            'last_message_at' => now(),
            'is_unread' => false,
        ]);

        return $message;
    }
}
