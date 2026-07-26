<?php

namespace App\Modules\WhatsApp\Services;

use App\Modules\WhatsApp\Models\WhatsAppConversation;

class ConversationWindowService
{
    public function isOpen(WhatsAppConversation $conversation): bool
    {
        return $conversation->isWindowOpen();
    }

    public function remainingSeconds(WhatsAppConversation $conversation): ?int
    {
        if ($conversation->window_expires_at === null) {
            return null;
        }

        if (! $conversation->isWindowOpen()) {
            return 0;
        }

        return max(0, (int) now()->diffInSeconds($conversation->window_expires_at, false));
    }
}
