<?php

namespace App\Providers;

use App\Modules\ACL\Models\Role;
use App\Modules\ACL\Policies\RolePolicy;
use App\Modules\ApiToken\Models\ApiToken;
use App\Modules\ApiToken\Policies\ApiTokenPolicy;
use App\Modules\Auth\Models\PersonalAccessToken;
use App\Modules\Auth\Providers\AuthUserProvider;
use App\Modules\Billing\Models\Invoice;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Policies\InvoicePolicy;
use App\Modules\Billing\Policies\PlanPolicy;
use App\Modules\Billing\Policies\SubscriptionPolicy;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Policies\TenantPolicy;
use App\Modules\User\Models\User;
use App\Modules\User\Policies\UserPolicy;
use App\Modules\Webhook\Models\Webhook;
use App\Modules\Webhook\Policies\WebhookPolicy;
use App\Modules\WhatsApp\Models\KanbanStage;
use App\Modules\WhatsApp\Models\WhatsAppConversation;
use App\Modules\WhatsApp\Models\WhatsAppTag;
use App\Modules\WhatsApp\Policies\ConversationPolicy;
use App\Modules\WhatsApp\Policies\KanbanPolicy;
use App\Modules\WhatsApp\Policies\TagPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Auth::provider('auth-user', function ($app, array $config): AuthUserProvider {
            return new AuthUserProvider($app['hash'], $config['model']);
        });

        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        $this->configureRateLimiting();
        $this->configurePolicies();
        $this->configureBroadcasting();
    }

    private function configureRateLimiting(): void
    {
        $byUserOrIp = fn (Request $request) => $request->user()?->getKey() ?: $request->ip();

        RateLimiter::for('api', function (Request $request) use ($byUserOrIp) {
            if (app()->environment('testing')) {
                return Limit::none();
            }

            return Limit::perMinute(60)->by($byUserOrIp($request));
        });

        RateLimiter::for('auth', function (Request $request) {
            if (app()->environment('testing')) {
                return Limit::none();
            }

            return Limit::perMinute(10)->by($request->ip());
        });

        RateLimiter::for('uploads', function (Request $request) use ($byUserOrIp) {
            if (app()->environment('testing')) {
                return Limit::none();
            }

            return Limit::perMinute(10)->by($byUserOrIp($request));
        });

        RateLimiter::for('whatsapp-send', function (Request $request) use ($byUserOrIp) {
            if (app()->environment('testing')) {
                return Limit::none();
            }

            return Limit::perMinute(30)->by($byUserOrIp($request));
        });

        RateLimiter::for('webhook-inbound', function (Request $request) {
            if (app()->environment('testing')) {
                return Limit::none();
            }

            $tenantUuid = $request->route('tenantUuid') ?? 'unknown';

            return Limit::perMinute(120)->by($request->ip().':'.$tenantUuid);
        });

        RateLimiter::for('api-tokens-create', function (Request $request) use ($byUserOrIp) {
            if (app()->environment('testing')) {
                return Limit::none();
            }

            return Limit::perMinute(5)->by($byUserOrIp($request));
        });
    }

    private function configurePolicies(): void
    {
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Tenant::class, TenantPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(ApiToken::class, ApiTokenPolicy::class);
        Gate::policy(Webhook::class, WebhookPolicy::class);
        Gate::policy(WhatsAppConversation::class, ConversationPolicy::class);
        Gate::policy(WhatsAppTag::class, TagPolicy::class);
        Gate::policy(KanbanStage::class, KanbanPolicy::class);
        Gate::policy(Plan::class, PlanPolicy::class);
        Gate::policy(Subscription::class, SubscriptionPolicy::class);
        Gate::policy(Invoice::class, InvoicePolicy::class);
    }

    private function configureBroadcasting(): void
    {
        Broadcast::routes(['middleware' => ['auth:sanctum']]);
    }
}
