<?php

namespace App\Modules\WhatsApp\Services;

use App\Modules\Tenant\Models\Tenant;
use App\Modules\WhatsApp\DTOs\IncomingMessageDTO;
use App\Modules\WhatsApp\DTOs\MessageStatusUpdateDTO;
use App\Modules\WhatsApp\DTOs\WebhookResultDTO;
use App\Modules\WhatsApp\Enums\ConversationStatus;
use App\Modules\WhatsApp\Enums\MessageDirection;
use App\Modules\WhatsApp\Enums\MessageStatus;
use App\Modules\WhatsApp\Enums\MessageType;
use App\Modules\WhatsApp\Events\MessageDelivered;
use App\Modules\WhatsApp\Events\MessageRead;
use App\Modules\WhatsApp\Events\MessageReceived;
use App\Modules\WhatsApp\Models\KanbanStage;
use App\Modules\WhatsApp\Models\WhatsAppContact;
use App\Modules\WhatsApp\Models\WhatsAppConversation;
use App\Modules\WhatsApp\Models\WhatsAppConversationStageMove;
use App\Modules\WhatsApp\Models\WhatsAppMessage;
use Illuminate\Support\Carbon;

class WhatsAppWebhookService
{
    public function handleNormalized(Tenant $tenant, WebhookResultDTO $result): void
    {
        foreach ($result->messages as $message) {
            $this->processIncomingMessage($tenant, $message);
        }

        foreach ($result->statuses as $status) {
            $this->processStatusUpdate($status);
        }
    }

    private function processIncomingMessage(Tenant $tenant, IncomingMessageDTO $incoming): void
    {
        $contact = $this->resolveContact($tenant, $incoming);
        $conversation = $this->resolveConversation($tenant, $contact);
        $messageType = MessageType::tryFrom($incoming->messageType) ?? MessageType::Unknown;
        $messageDate = $incoming->receivedAt instanceof \DateTimeInterface
            ? Carbon::parse($incoming->receivedAt)
            : now();

        $storedMessage = WhatsAppMessage::query()->updateOrCreate(
            ['external_message_id' => $incoming->externalMessageId],
            [
                'conversation_id' => $conversation->id,
                'direction' => MessageDirection::Inbound->value,
                'message_type' => $messageType->value,
                'content' => $incoming->content,
                'media' => $incoming->media,
                'status' => MessageStatus::Received->value,
            ]
        );

        $conversation->update([
            'last_message_preview' => $this->buildPreview($messageType, $incoming->content),
            'last_message_at' => $messageDate,
            'last_customer_message_at' => $messageDate,
            'window_expires_at' => $messageDate->copy()->addHours(24),
            'is_unread' => true,
            'status' => ConversationStatus::Open->value,
        ]);

        broadcast(new MessageReceived(
            (string) $tenant->uuid,
            $conversation->id,
            $storedMessage->toArray(),
        ));
    }

    private function processStatusUpdate(MessageStatusUpdateDTO $status): void
    {
        $message = WhatsAppMessage::query()
            ->where('external_message_id', $status->externalMessageId)
            ->first();

        if (! $message) {
            return;
        }

        $messageStatus = match ($status->status) {
            'sent' => MessageStatus::Sent,
            'delivered' => MessageStatus::Delivered,
            'read' => MessageStatus::Read,
            'failed' => MessageStatus::Failed,
            default => null,
        };

        if ($messageStatus === null) {
            return;
        }

        $occurredAt = $status->occurredAt
            ? Carbon::parse($status->occurredAt)
            : now();

        $updates = ['status' => $messageStatus->value];

        if ($messageStatus === MessageStatus::Delivered) {
            $updates['delivered_at'] = $occurredAt;
        }

        if ($messageStatus === MessageStatus::Read) {
            $updates['read_at'] = $occurredAt;
        }

        if ($status->error !== null) {
            $updates['metadata'] = array_merge($message->metadata ?? [], $status->error->toMetadata());
        }

        $message->update($updates);

        $conversation = $message->conversation()->first();

        if ($conversation && $messageStatus === MessageStatus::Delivered) {
            broadcast(new MessageDelivered(
                $conversation->tenantUuid(),
                $conversation->id,
                $status->externalMessageId,
                $messageStatus->value,
                $occurredAt->toIso8601String(),
            ));
        }

        if ($conversation && $messageStatus === MessageStatus::Read) {
            broadcast(new MessageRead(
                $conversation->tenantUuid(),
                $conversation->id,
                $status->externalMessageId,
                $messageStatus->value,
                $occurredAt->toIso8601String(),
            ));
        }
    }

    private function resolveContact(Tenant $tenant, IncomingMessageDTO $incoming): WhatsAppContact
    {
        $profileName = $incoming->profileName ?? $incoming->externalContactId;

        return WhatsAppContact::query()->withoutGlobalScopes()->firstOrCreate(
            [
                'tenant_id' => $tenant->id,
                'external_contact_id' => $incoming->externalContactId,
            ],
            [
                'profile_name' => $profileName,
                'display_name' => $profileName,
            ]
        );
    }

    private function resolveConversation(Tenant $tenant, WhatsAppContact $contact): WhatsAppConversation
    {
        $conversation = WhatsAppConversation::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('contact_id', $contact->id)
            ->first();

        if (! $conversation) {
            $firstStage = KanbanStage::query()->withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->orderBy('sort_order')
                ->first();

            $conversation = WhatsAppConversation::query()->withoutGlobalScopes()->create([
                'tenant_id' => $tenant->id,
                'contact_id' => $contact->id,
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
