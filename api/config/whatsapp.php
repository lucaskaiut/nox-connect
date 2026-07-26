<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Provider ativo (global da aplicação)
    |--------------------------------------------------------------------------
    */

    'provider' => env('WHATSAPP_PROVIDER', 'meta'),

    /*
    |--------------------------------------------------------------------------
    | Credenciais globais do provider
    |--------------------------------------------------------------------------
    |
    | Nunca persistir em banco / nunca expor secrets no frontend.
    | D-API: public_key (publishable) + secret_key (API key secreta).
    |
    */

    'credentials' => [
        'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
        'app_id' => env('WHATSAPP_APP_ID'),
        'app_secret' => env('WHATSAPP_APP_SECRET'),
        'public_key' => env('WHATSAPP_PUBLIC_KEY'),
        'secret_key' => env('WHATSAPP_SECRET_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | D-API
    |--------------------------------------------------------------------------
    */

    'd_api' => [
        'base_url' => env('WHATSAPP_DAPI_BASE_URL', 'https://api.d-api.cloud'),
        'connect_base_url' => env('WHATSAPP_DAPI_CONNECT_BASE_URL', 'https://connect.d-api.cloud'),
        // URL pública do SaaS para webhooks (ex.: https://xxxx.ngrok.io). Vazio = usa APP_URL.
        // Em localhost o webhook é omitido no SDK para não quebrar o Embedded Signup.
        'webhook_base_url' => env('WHATSAPP_WEBHOOK_BASE_URL'),
        // standard = número novo na API Oficial | coexistence = manter app WhatsApp Business
        'connect_mode' => env('WHATSAPP_DAPI_CONNECT_MODE', 'standard'),
        // Em falha do Embedded Signup, mantém o popup aberto para inspecionar Network/Console.
        'keep_popup_on_error' => (bool) env('WHATSAPP_DAPI_KEEP_POPUP_ON_ERROR', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Implementações — operações (envio) e onboarding/conexão
    |--------------------------------------------------------------------------
    */

    'providers' => [
        'meta' => App\Modules\WhatsApp\Infrastructure\Providers\Meta\MetaCloudProvider::class,
        'd-api' => App\Modules\WhatsApp\Infrastructure\Providers\DApi\DApiProvider::class,
    ],

    'connection_providers' => [
        'meta' => App\Modules\WhatsApp\Infrastructure\Providers\Meta\MetaConnectionProvider::class,
        'd-api' => App\Modules\WhatsApp\Infrastructure\Providers\DApi\DApiConnectionProvider::class,
    ],

    'webhook_normalizers' => [
        'meta' => App\Modules\WhatsApp\Infrastructure\Providers\Meta\MetaWebhookNormalizer::class,
        'd-api' => App\Modules\WhatsApp\Infrastructure\Providers\DApi\DApiWebhookNormalizer::class,
    ],

];
