<?php

namespace App\Modules\WhatsApp\Providers;

use App\Modules\Webhook\Services\WebhookEventRegistry;
use App\Modules\WhatsApp\Services\WhatsAppCloudApi;
use Illuminate\Support\ServiceProvider;

class WhatsAppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(WhatsAppCloudApi::class);
    }

    public function boot(): void
    {
        $this->app->make(WebhookEventRegistry::class)->register([
            'whatsapp.message.received' => 'Mensagem recebida',
            'whatsapp.message.sent' => 'Mensagem enviada',
            'whatsapp.conversation.assigned' => 'Atendimento atribuído',
            'whatsapp.conversation.transferred' => 'Atendimento transferido',
            'whatsapp.conversation.closed' => 'Conversa finalizada',
            'whatsapp.stage.moved' => 'Card movido no Kanban',
            'whatsapp.tag.added' => 'Tag adicionada à conversa',
        ]);
    }
}
