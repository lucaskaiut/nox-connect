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
            $message = $this->normalizeReceivedMessage($data);

            if ($message !== null) {
                $messages[] = $message;
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

    /**
     * @param  array<string, mixed>  $data
     */
    private function normalizeReceivedMessage(array $data): ?IncomingMessageDTO
    {
        $fromMe = (bool) Arr::get($data, 'fromMe', false);
        $externalMessageId = Arr::get($data, 'id');

        // Inbound: contato = from. Outbound (echo/coexistência): contato = to.
        $contactJid = $fromMe
            ? (string) Arr::get($data, 'to.jid', '')
            : (string) Arr::get($data, 'from.jid', '');
        $externalContactId = $this->jidToPhone($contactJid);

        if (! filled($externalMessageId) || ! filled($externalContactId)) {
            return null;
        }

        $type = (string) Arr::get($data, 'type', 'text');
        $messageType = MessageType::tryFrom($type)?->value ?? MessageType::Unknown->value;
        $content = Arr::get($data, 'message');
        $media = $this->extractMedia($data, $messageType);

        return new IncomingMessageDTO(
            externalMessageId: (string) $externalMessageId,
            externalContactId: $externalContactId,
            messageType: $messageType,
            content: is_string($content) ? $content : null,
            media: $media,
            profileName: $fromMe
                ? null
                : (Arr::get($data, 'from_name') ?? Arr::get($data, 'from.name')),
            receivedAt: $this->resolveReceivedAt($data),
            direction: $fromMe ? 'outbound' : 'inbound',
            raw: $data,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{url?: string|null, mime_type?: string|null, file_length?: int|null}|null
     */
    private function extractMedia(array $data, string $messageType): ?array
    {
        if (! in_array($messageType, [
            MessageType::Image->value,
            MessageType::Video->value,
            MessageType::Audio->value,
            MessageType::Document->value,
        ], true)) {
            return null;
        }

        $url = Arr::get($data, 'media_url') ?? Arr::get($data, 'media_data.url');
        $mimeType = Arr::get($data, 'media_data.mimetype');
        $fileLength = Arr::get($data, 'media_data.file_length');

        if (! filled($url) && ! filled($mimeType)) {
            return null;
        }

        return array_filter([
            'url' => is_string($url) ? $url : null,
            'mime_type' => is_string($mimeType) ? $mimeType : null,
            'file_length' => is_numeric($fileLength) ? (int) $fileLength : null,
        ], fn (mixed $value): bool => $value !== null);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveReceivedAt(array $data): Carbon
    {
        $ts = Arr::get($data, 'timestamp');

        if (! is_numeric($ts)) {
            return now();
        }

        $ts = (int) $ts;
        $timezone = (string) config('app.timezone', 'UTC');

        // Unix epoch é absoluto; materializa no timezone do app para o Eloquent
        // não gravar o relógio UTC e reler como horário local (vira "futuro").
        return $ts > 1_000_000_000_000
            ? Carbon::createFromTimestampMs($ts, $timezone)
            : Carbon::createFromTimestamp($ts, $timezone);
    }

    private function jidToPhone(string $jid): string
    {
        if ($jid === '') {
            return '';
        }

        return explode('@', $jid)[0] ?? $jid;
    }
}
