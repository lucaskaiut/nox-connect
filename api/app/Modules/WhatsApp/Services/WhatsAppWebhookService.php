<?php

namespace App\Modules\WhatsApp\Services;

use App\Modules\WhatsApp\Enums\ConversationStatus;
use App\Modules\WhatsApp\Enums\MessageDirection;
use App\Modules\WhatsApp\Enums\MessageStatus;
use App\Modules\WhatsApp\Enums\MessageType;
use App\Modules\WhatsApp\Events\MessageDelivered;
use App\Modules\WhatsApp\Events\MessageRead;
use App\Modules\WhatsApp\Events\MessageReceived;
use App\Modules\WhatsApp\Models\KanbanStage;
use App\Modules\WhatsApp\Models\WhatsAppConfig;
use App\Modules\WhatsApp\Models\WhatsAppContact;
use App\Modules\WhatsApp\Models\WhatsAppConversation;
use App\Modules\WhatsApp\Models\WhatsAppConversationStageMove;
use App\Modules\WhatsApp\Models\WhatsAppMessage;
use Illuminate\Support\Arr;

class WhatsAppWebhookService
{
    public function __construct(
        private readonly WhatsAppCloudApi $cloudApi,
    ) {}

    public function handleWebhook(WhatsAppConfig $config, array $payload): void
    {
        $entries = Arr::get($payload, 'entry', []);

        foreach ($entries as $entry) {
            $changes = Arr::get($entry, 'changes', []);

            foreach ($changes as $change) {
                $value = Arr::get($change, 'value', []);

                if (Arr::get($value, 'messaging_product') !== 'whatsapp') {
                    continue;
                }

                $this->processMetadata($value);
                $this->processMessages($config, $value);
                $this->processStatuses($config, $value);
            }
        }
    }

    private function processMetadata(array $value): void
    {
        // Optional: handle phone_number_id, display_phone_number metadata
    }

    private function processMessages(WhatsAppConfig $config, array $value): void
    {
        $messages = Arr::get($value, 'messages', []);

        foreach ($messages as $message) {
            if (Arr::get($message, 'type') === 'unsupported') {
                continue;
            }

            $from = Arr::get($message, 'from');
            $waMessageId = Arr::get($message, 'id');

            if (blank($from) || blank($waMessageId)) {
                continue;
            }

            $contact = $this->resolveContact($config, $from, $value);
            $conversation = $this->resolveConversation($config, $contact);
            $messageType = $this->mapMessageType(Arr::get($message, 'type', 'unknown'));

            $content = null;
            $media = null;

            if ($messageType === MessageType::Text) {
                $content = Arr::get($message, 'text.body');
            } else {
                $mediaType = $messageType->value;
                $mediaData = Arr::get($message, $messageType->value, []);

                if ($mediaType === 'image' || $mediaType === 'video') {
                    $content = Arr::get($mediaData, 'caption');
                }

                $media = [
                    'id' => Arr::get($mediaData, 'id'),
                    'mime_type' => Arr::get($mediaData, 'mime_type'),
                    'sha256' => Arr::get($mediaData, 'sha256'),
                ];
            }

            WhatsAppMessage::query()->updateOrCreate(
                ['wa_message_id' => $waMessageId],
                [
                    'conversation_id' => $conversation->id,
                    'direction' => MessageDirection::Inbound->value,
                    'message_type' => $messageType->value,
                    'content' => $content,
                    'media' => $media,
                    'status' => MessageStatus::Received->value,
                ]
            );

            $conversation->update([
                'last_message_preview' => $this->buildPreview($messageType, $content),
                'last_message_at' => now(),
                'is_unread' => true,
                'status' => ConversationStatus::Open->value,
            ]);

            broadcast(new MessageReceived(
                $config->tenant_id,
                $conversation->id,
                $message->fresh()->toArray(),
            ))->toOthers();
        }
    }

    private function processStatuses(WhatsAppConfig $config, array $value): void
    {
        $statuses = Arr::get($value, 'statuses', []);

        foreach ($statuses as $status) {
            $waMessageId = Arr::get($status, 'id');
            $statusType = Arr::get($status, 'status');

            if (blank($waMessageId)) {
                continue;
            }

            $message = WhatsAppMessage::query()->where('wa_message_id', $waMessageId)->first();

            if (! $message) {
                continue;
            }

            $messageStatus = match ($statusType) {
                'sent' => MessageStatus::Sent,
                'delivered' => MessageStatus::Delivered,
                'read' => MessageStatus::Read,
                'failed' => MessageStatus::Failed,
                default => null,
            };

            if ($messageStatus === null) {
                continue;
            }

            $updates = ['status' => $messageStatus->value];

            if ($messageStatus === MessageStatus::Delivered) {
                $updates['delivered_at'] = now();
            }

            if ($messageStatus === MessageStatus::Read) {
                $updates['read_at'] = now();
            }

            $message->update($updates);

            $conversation = $message->conversation()->first();

            if ($conversation && $messageStatus === MessageStatus::Delivered) {
                broadcast(new MessageDelivered(
                    $conversation->tenant_id,
                    $conversation->id,
                    $waMessageId,
                    $messageStatus->value,
                    now(),
                ))->toOthers();
            }

            if ($conversation && $messageStatus === MessageStatus::Read) {
                broadcast(new MessageRead(
                    $conversation->tenant_id,
                    $conversation->id,
                    $waMessageId,
                    $messageStatus->value,
                    now(),
                ))->toOthers();
            }
        }
    }

    private function resolveContact(WhatsAppConfig $config, string $waId, array $value): WhatsAppContact
    {
        $contactInfo = collect(Arr::get($value, 'contacts', []))
            ->firstWhere('wa_id', $waId);

        $profileName = Arr::get($contactInfo, 'profile.name') ?? $waId;

        return WhatsAppContact::query()->firstOrCreate(
            ['wa_id' => $waId],
            [
                'tenant_id' => $config->tenant_id,
                'profile_name' => $profileName,
                'display_name' => $profileName,
            ]
        );
    }

    private function resolveConversation(WhatsAppConfig $config, WhatsAppContact $contact): WhatsAppConversation
    {
        $conversation = WhatsAppConversation::query()
            ->where('contact_id', $contact->id)
            ->where('whatsapp_config_id', $config->id)
            ->first();

        if (! $conversation) {
            $firstStage = KanbanStage::query()
                ->where('tenant_id', $config->tenant_id)
                ->orderBy('sort_order')
                ->first();

            $conversation = WhatsAppConversation::query()->create([
                'tenant_id' => $config->tenant_id,
                'contact_id' => $contact->id,
                'whatsapp_config_id' => $config->id,
                'status' => ConversationStatus::Open->value,
                'current_stage_id' => $firstStage?->id,
                'last_message_at' => now(),
            ]);

            if ($firstStage) {
                WhatsAppConversationStageMove::query()->create([
                    'conversation_id' => $conversation->id,
                    'stage_id' => $firstStage->id,
                    'moved_at' => now(),
                ]);
            }
        }

        return $conversation;
    }

    private function mapMessageType(string $type): MessageType
    {
        return MessageType::tryFrom($type) ?? MessageType::Unknown;
    }

    private function buildPreview(MessageType $type, ?string $content): string
    {
        return match ($type) {
            MessageType::Text => mb_strimwidth($content ?? '', 0, 120, '...'),
            MessageType::Image => '📷 Imagem',
            MessageType::Video => '🎬 Vídeo',
            MessageType::Audio => '🎵 Áudio',
            MessageType::Document => '📄 Documento',
            MessageType::Location => '📍 Localização',
            MessageType::Contacts => '👤 Contato',
            default => 'Mensagem',
        };
    }
}
