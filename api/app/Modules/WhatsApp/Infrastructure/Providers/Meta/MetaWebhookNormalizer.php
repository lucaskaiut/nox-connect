<?php

namespace App\Modules\WhatsApp\Infrastructure\Providers\Meta;

use App\Modules\Tenant\Models\Tenant;
use App\Modules\WhatsApp\Contracts\WebhookNormalizer;
use App\Modules\WhatsApp\DTOs\IncomingMessageDTO;
use App\Modules\WhatsApp\DTOs\MessageStatusUpdateDTO;
use App\Modules\WhatsApp\DTOs\ProviderErrorDTO;
use App\Modules\WhatsApp\DTOs\WebhookChallengeDTO;
use App\Modules\WhatsApp\DTOs\WebhookResultDTO;
use App\Modules\WhatsApp\Enums\MessageType;
use App\Modules\WhatsApp\Enums\WhatsAppProviderKey;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

final class MetaWebhookNormalizer implements WebhookNormalizer
{
    public function providerKey(): string
    {
        return WhatsAppProviderKey::Meta->value;
    }

    public function verify(Tenant $tenant, Request $request): ?WebhookChallengeDTO
    {
        if (! $request->isMethod('get')) {
            return null;
        }

        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        if ($mode === 'subscribe' && $token === $tenant->whatsappSetting('webhook_verify_token')) {
            return new WebhookChallengeDTO(
                valid: true,
                challenge: is_scalar($challenge) ? (string) $challenge : null,
                status: 200,
            );
        }

        return new WebhookChallengeDTO(valid: false, status: 403);
    }

    public function normalize(Tenant $tenant, Request $request): WebhookResultDTO
    {
        unset($tenant);

        $payload = $request->all();
        $messages = [];
        $statuses = [];

        foreach (Arr::get($payload, 'entry', []) as $entry) {
            foreach (Arr::get($entry, 'changes', []) as $change) {
                $value = Arr::get($change, 'value', []);

                if (Arr::get($value, 'messaging_product') !== 'whatsapp') {
                    continue;
                }

                foreach (Arr::get($value, 'messages', []) as $incoming) {
                    if (Arr::get($incoming, 'type') === 'unsupported') {
                        continue;
                    }

                    $from = Arr::get($incoming, 'from');
                    $externalMessageId = Arr::get($incoming, 'id');

                    if (blank($from) || blank($externalMessageId)) {
                        continue;
                    }

                    $type = Arr::get($incoming, 'type', 'unknown');
                    $messageType = MessageType::tryFrom($type)?->value ?? MessageType::Unknown->value;

                    $content = null;
                    $media = null;

                    if ($messageType === MessageType::Text->value) {
                        $content = Arr::get($incoming, 'text.body');
                    } else {
                        $mediaData = Arr::get($incoming, $messageType, []);

                        if (in_array($messageType, ['image', 'video'], true)) {
                            $content = Arr::get($mediaData, 'caption');
                        }

                        $media = [
                            'id' => Arr::get($mediaData, 'id'),
                            'mime_type' => Arr::get($mediaData, 'mime_type'),
                            'sha256' => Arr::get($mediaData, 'sha256'),
                        ];
                    }

                    $contactInfo = collect(Arr::get($value, 'contacts', []))
                        ->firstWhere('wa_id', $from);

                    $messages[] = new IncomingMessageDTO(
                        externalMessageId: (string) $externalMessageId,
                        externalContactId: (string) $from,
                        messageType: $messageType,
                        content: $content,
                        media: $media,
                        profileName: Arr::get($contactInfo, 'profile.name'),
                        receivedAt: now(),
                        raw: $incoming,
                    );
                }

                foreach (Arr::get($value, 'statuses', []) as $status) {
                    $externalMessageId = Arr::get($status, 'id');
                    $statusType = Arr::get($status, 'status');

                    if (blank($externalMessageId) || blank($statusType)) {
                        continue;
                    }

                    $error = null;
                    if ($statusType === 'failed' && is_array(Arr::get($status, 'errors.0'))) {
                        $error = ProviderErrorDTO::fromArray(Arr::get($status, 'errors.0'));
                    }

                    $statuses[] = new MessageStatusUpdateDTO(
                        externalMessageId: (string) $externalMessageId,
                        status: (string) $statusType,
                        occurredAt: now(),
                        error: $error,
                        raw: $status,
                    );
                }
            }
        }

        return new WebhookResultDTO(
            messages: $messages,
            statuses: $statuses,
            raw: $payload,
        );
    }
}
