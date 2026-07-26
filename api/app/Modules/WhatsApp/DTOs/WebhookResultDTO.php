<?php

namespace App\Modules\WhatsApp\DTOs;

final readonly class WebhookResultDTO
{
    /**
     * @param  list<IncomingMessageDTO>  $messages
     * @param  list<MessageStatusUpdateDTO>  $statuses
     */
    public function __construct(
        public array $messages = [],
        public array $statuses = [],
        public array $raw = [],
    ) {}
}
