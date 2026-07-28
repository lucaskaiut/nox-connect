<?php

namespace App\Modules\WhatsApp\Infrastructure\Providers\DApi;

use App\Modules\Tenant\Models\Tenant;
use App\Modules\WhatsApp\Contracts\WebhookNormalizer;
use App\Modules\WhatsApp\DTOs\IncomingMessageDTO;
use App\Modules\WhatsApp\DTOs\MessageStatusUpdateDTO;
use App\Modules\WhatsApp\DTOs\WebhookChallengeDTO;
use App\Modules\WhatsApp\DTOs\WebhookResultDTO;
use App\Modules\WhatsApp\Enums\MessageType;
use App\Modules\WhatsApp\Enums\WhatsAppProviderKey;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

final class DApiWebhookNormalizer implements WebhookNormalizer
{
    public function providerKey(): string
    {
        return WhatsAppProviderKey::DApi->value;
    }

    public function verify(Tenant $tenant, Request $request): ?WebhookChallengeDTO
    {
        // D-API não usa hub.challenge da Meta; GET sem payload é ignorado.
        unset($tenant);

        if ($request->isMethod('get')) {
            return new WebhookChallengeDTO(valid: true, challenge: 'ok', status: 200);
        }

        return null;
    }

    public function normalize(Tenant $tenant, Request $request): WebhookResultDTO
    {
        unset($tenant);

        $payload = $request->all();
        $event = (string) Arr::get($payload, 'event', '');
        $data = Arr::get($payload, 'data', []);
        $messages = [];
        $statuses = [];

        if ($event === 'messages.received' && is_array($data)) {
            $fromMe = (bool) Arr::get($data, 'fromMe', false);

            if (! $fromMe) {
                $externalMessageId = Arr::get($data, 'id');
                $fromJid = (string) Arr::get($data, 'from.jid', '');
                $externalContactId = $this->jidToPhone($fromJid);

                if (filled($externalMessageId) && filled($externalContactId)) {
                    $type = (string) Arr::get($data, 'type', 'text');
                    $messageType = MessageType::tryFrom($type)?->value ?? MessageType::Unknown->value;
                    $content = Arr::get($data, 'message');
                    $media = null;

                    if (in_array($messageType, [
                        MessageType::Image->value,
                        MessageType::Video->value,
                        MessageType::Audio->value,
                        MessageType::Document->value,
                    ], true)) {
                        $media = [
                            'url' => Arr::get($data, 'media_url') ?? Arr::get($data, 'media_data.url'),
                            'mime_type' => Arr::get($data, 'media_data.mimetype'),
                        ];
                    }

                    $receivedAt = now();
                    $ts = Arr::get($data, 'timestamp');

                    if (is_numeric($ts)) {
                        $ts = (int) $ts;
                        // D-API/WhatsApp enviam Unix em segundos (~1e9). ms seria ~1e12.
                        $receivedAt = $ts > 1_000_000_000_000
                            ? Carbon::createFromTimestampMs($ts)
                            : Carbon::createFromTimestamp($ts);
                    }

                    $messages[] = new IncomingMessageDTO(
                        externalMessageId: (string) $externalMessageId,
                        externalContactId: $externalContactId,
                        messageType: $messageType,
                        content: is_string($content) ? $content : null,
                        media: $media,
                        profileName: Arr::get($data, 'from_name') ?? Arr::get($data, 'from.name'),
                        receivedAt: $receivedAt,
                        raw: $data,
                    );
                }
            }
        }

        if (in_array($event, ['message.delivered', 'message.read', 'message.failed'], true) && is_array($data)) {
            $externalMessageId = Arr::get($data, 'message_id') ?? Arr::get($data, 'id');
            $status = match ($event) {
                'message.delivered' => 'delivered',
                'message.read' => 'read',
                'message.failed' => 'failed',
                default => null,
            };

            if (filled($externalMessageId) && $status !== null) {
                $statuses[] = new MessageStatusUpdateDTO(
                    externalMessageId: (string) $externalMessageId,
                    status: $status,
                    occurredAt: now(),
                    error: null,
                    raw: $data,
                );
            }
        }

        return new WebhookResultDTO(
            messages: $messages,
            statuses: $statuses,
            raw: $payload,
        );
    }

    private function jidToPhone(string $jid): string
    {
        if ($jid === '') {
            return '';
        }

        return explode('@', $jid)[0] ?? $jid;
    }
}
