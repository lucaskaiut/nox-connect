<?php

namespace App\Providers;

use App\Modules\ACL\Models\Role;
use App\Modules\ACL\Policies\RolePolicy;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Policies\TenantPolicy;
use App\Modules\User\Models\User;
use App\Modules\User\Policies\UserPolicy;
use App\Modules\Webhook\Models\Webhook;
use App\Modules\Webhook\Policies\WebhookPolicy;
use App\Modules\WhatsApp\Models\KanbanStage;
use App\Modules\WhatsApp\Models\WhatsAppConfig;
use App\Modules\WhatsApp\Models\WhatsAppConversation;
use App\Modules\WhatsApp\Models\WhatsAppTag;
use App\Modules\WhatsApp\Policies\ConversationPolicy;
use App\Modules\WhatsApp\Policies\KanbanPolicy;
use App\Modules\WhatsApp\Policies\TagPolicy;
use App\Modules\WhatsApp\Policies\WhatsAppConfigPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

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
        $this->configureRateLimiting();
        $this->configurePolicies();
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->getKey() ?: $request->ip());
        });

        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });


    }

    private function configurePolicies(): void
    {
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Tenant::class, TenantPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(Webhook::class, WebhookPolicy::class);
        Gate::policy(WhatsAppConfig::class, WhatsAppConfigPolicy::class);
        Gate::policy(WhatsAppConversation::class, ConversationPolicy::class);
        Gate::policy(WhatsAppTag::class, TagPolicy::class);
        Gate::policy(KanbanStage::class, KanbanPolicy::class);
    }
}
