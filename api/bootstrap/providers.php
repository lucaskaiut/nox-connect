<?php

use App\Modules\Billing\Providers\BillingServiceProvider;
use App\Modules\Onboarding\Providers\OnboardingServiceProvider;
use App\Modules\Tenant\Providers\TenantServiceProvider;
use App\Modules\Webhook\Providers\WebhookServiceProvider;
use App\Modules\WhatsApp\Providers\WhatsAppServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    TenantServiceProvider::class,
    OnboardingServiceProvider::class,
    WebhookServiceProvider::class,
    WhatsAppServiceProvider::class,
    BillingServiceProvider::class,
];
