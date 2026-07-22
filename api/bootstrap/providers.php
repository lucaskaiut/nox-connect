<?php

use App\Modules\Tenant\Providers\TenantServiceProvider;
use App\Modules\Webhook\Providers\WebhookServiceProvider;
use App\Modules\WhatsApp\Providers\WhatsAppServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    TenantServiceProvider::class,
    WebhookServiceProvider::class,
    WhatsAppServiceProvider::class,
];
