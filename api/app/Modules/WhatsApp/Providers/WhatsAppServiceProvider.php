<?php

namespace App\Modules\WhatsApp\Providers;

use App\Modules\Webhook\Services\WebhookEventRegistry;
use App\Modules\WhatsApp\Contracts\WhatsAppConnectionProvider;
use App\Modules\WhatsApp\Contracts\WhatsAppProvider;
use App\Modules\WhatsApp\Infrastructure\Factories\WhatsAppConnectionProviderFactory;
use App\Modules\WhatsApp\Infrastructure\Factories\WhatsAppProviderFactory;
use App\Modules\WhatsApp\Infrastructure\Providers\Meta\MetaGraphClient;
use App\Modules\WhatsApp\Infrastructure\Providers\Meta\MetaWebhookNormalizer;
use App\Modules\WhatsApp\Infrastructure\Webhooks\WebhookNormalizerRegistry;
use App\Modules\WhatsApp\Services\ConversationWindowService;
use App\Modules\WhatsApp\Services\MessageTemplateService;
use App\Modules\WhatsApp\Services\TemplateMessageService;
use Illuminate\Support\ServiceProvider;

class WhatsAppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MetaGraphClient::class);
        $this->app->singleton(MetaWebhookNormalizer::class);
        $this->app->singleton(WhatsAppProviderFactory::class);
        $this->app->singleton(WhatsAppConnectionProviderFactory::class);
        $this->app->singleton(WebhookNormalizerRegistry::class);
        $this->app->singleton(MessageTemplateService::class);
        $this->app->singleton(ConversationWindowService::class);
        $this->app->singleton(TemplateMessageService::class);

        $this->app->singleton(WhatsAppProvider::class, function ($app) {
            return $app->make(WhatsAppProviderFactory::class)->make();
        });

        $this->app->singleton(WhatsAppConnectionProvider::class, function ($app) {
            return $app->make(WhatsAppConnectionProviderFactory::class)->make();
        });
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
