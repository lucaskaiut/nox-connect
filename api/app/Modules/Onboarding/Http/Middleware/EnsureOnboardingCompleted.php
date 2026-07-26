<?php

namespace App\Modules\Onboarding\Http\Middleware;

use App\Modules\Shared\Http\ApiError;
use App\Modules\Tenant\Support\Facades\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOnboardingCompleted
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = TenantContext::tenant();

        if ($tenant === null || ! $tenant->needsOnboarding()) {
            return $next($request);
        }

        if ($this->isAllowedDuringOnboarding($request)) {
            return $next($request);
        }

        return ApiError::response(
            'Conclua o onboarding para acessar a aplicação.',
            403,
            ['code' => 'ONBOARDING_REQUIRED'],
        );
    }

    private function isAllowedDuringOnboarding(Request $request): bool
    {
        return $request->is(
            'api/onboarding',
            'api/onboarding/*',
            'api/auth/*',
            'api/webhooks/*',
        );
    }
}
