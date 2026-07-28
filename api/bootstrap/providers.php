<?php

return [
    App\Modules\Billing\Providers\BillingServiceProvider::class,
    App\Modules\Onboarding\Providers\OnboardingServiceProvider::class,
    App\Modules\Tenant\Providers\TenantServiceProvider::class,
    App\Modules\Webhook\Providers\WebhookServiceProvider::class,
    App\Modules\WhatsApp\Providers\WhatsAppServiceProvider::class,
    App\Providers\AppServiceProvider::class,
    App\Providers\TelescopeServiceProvider::class,
];
