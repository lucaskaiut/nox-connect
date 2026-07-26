<?php

namespace App\Modules\Onboarding\Providers;

use App\Modules\Onboarding\Services\OnboardingService;
use Illuminate\Support\ServiceProvider;

class OnboardingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(OnboardingService::class);
    }
}
