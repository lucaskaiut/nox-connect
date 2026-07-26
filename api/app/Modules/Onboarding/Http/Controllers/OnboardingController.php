<?php

namespace App\Modules\Onboarding\Http\Controllers;

use App\Modules\Onboarding\Http\Requests\CompleteCompanyRequest;
use App\Modules\Onboarding\Http\Requests\CompleteWhatsAppRequest;
use App\Modules\Onboarding\Services\OnboardingService;
use App\Modules\Shared\Http\Controllers\ApiController;
use App\Modules\Tenant\Support\Facades\TenantContext;
use Illuminate\Http\JsonResponse;

class OnboardingController extends ApiController
{
    public function __construct(
        private readonly OnboardingService $service,
    ) {}

    public function show(): JsonResponse
    {
        return $this->success($this->service->status(TenantContext::tenant()));
    }

    public function completeCompany(CompleteCompanyRequest $request): JsonResponse
    {
        $status = $this->service->completeCompany(
            TenantContext::tenant(),
            $request->validated(),
        );

        return $this->success($status, 'Dados da empresa salvos.');
    }

    public function initializeWhatsApp(): JsonResponse
    {
        $init = $this->service->initializeWhatsApp(TenantContext::tenant());

        return $this->success($init->toArray());
    }

    public function completeWhatsApp(CompleteWhatsAppRequest $request): JsonResponse
    {
        $status = $this->service->completeWhatsApp(
            TenantContext::tenant(),
            $request->validated(),
        );

        return $this->success($status, $status['connection_message'] ?? 'WhatsApp conectado.');
    }

    public function finish(): JsonResponse
    {
        $status = $this->service->finish(TenantContext::tenant());

        return $this->success($status, 'Onboarding concluído.');
    }
}
