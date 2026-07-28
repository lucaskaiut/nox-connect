<?php

namespace App\Modules\WhatsApp\Services;

use App\Modules\Tenant\Models\Tenant;
use App\Modules\WhatsApp\Contracts\WhatsAppProvider;
use App\Modules\WhatsApp\DTOs\SendTemplateDTO;
use App\Modules\WhatsApp\Enums\MessageDirection;
use App\Modules\WhatsApp\Enums\MessageStatus;
use App\Modules\WhatsApp\Events\MessageSent;
use App\Modules\WhatsApp\Models\WhatsAppConversation;
use App\Modules\WhatsApp\Models\WhatsAppMessage;

class TemplateMessageService
{
    public function __construct(
        private readonly WhatsAppProvider $provider,
    ) {}

    public function send(WhatsAppConversation $conversation, string $templateName, string $language, array $variables): WhatsAppMessage
    {
        $tenant = $conversation->relationLoaded('tenant') && $conversation->tenant
            ? $conversation->tenant
            : Tenant::query()->findOrFail($conversation->tenant_id);

        $contact = $conversation->contact()->firstOrFail();

        $assignment = $conversation->currentAssignment()->with('user')->first();
        $senderName = $assignment?->user?->name;

        $result = $this->provider->sendTemplate(new SendTemplateDTO(
            tenant: $tenant,
            to: $contact->external_contact_id,
            templateName: $templateName,
            language: $language,
            variables: $variables,
        ));

        $status = $result->success ? MessageStatus::Sent->value : MessageStatus::Failed->value;

        $metadata = $senderName ? ['sender_name' => $senderName] : null;

        if ($result->error !== null) {
            $metadata = array_merge($metadata ?? [], $result->error->toMetadata());
        }

        $preview = $variables
            ? mb_strimwidth('Template: ' . implode(', ', $variables), 0, 120, '...')
            : 'Template: ' . $templateName;

        $message = WhatsAppMessage::query()->create([
            'tenant_id' => $conversation->tenant_id,
            'conversation_id' => $conversation->id,
            'direction' => MessageDirection::Outbound->value,
            'message_type' => 'template',
            'content' => $preview,
            'status' => $status,
            'external_message_id' => $result->externalMessageId,
            'metadata' => $metadata,
        ]);

        $conversation->update([
            'last_message_preview' => $result->error
                ? '❌ ' . mb_strimwidth($result->error->message ?? 'Erro ao enviar template', 0, 100, '...')
                : $preview,
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
}
