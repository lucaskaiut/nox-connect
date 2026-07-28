<?php

namespace App\Modules\Onboarding\Services;

use App\Modules\Audit\Enums\AuditAction;
use App\Modules\Audit\Services\AuditLogService;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Services\TenantService;
use App\Modules\WhatsApp\Contracts\WhatsAppConnectionProvider;
use App\Modules\WhatsApp\DTOs\ConnectionInitializationDTO;
use Illuminate\Validation\ValidationException;

class OnboardingService
{
    public function __construct(
        private readonly TenantService $tenants,
        private readonly WhatsAppConnectionProvider $connectionProvider,
        private readonly AuditLogService $audit,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function status(Tenant $tenant): array
    {
        $onboarding = $tenant->onboardingSettings();

        return [
            'required' => $tenant->needsOnboarding(),
            'completed' => $tenant->isOnboardingCompleted(),
            'company_completed' => (bool) ($onboarding['company_completed'] ?? false),
            'whatsapp_completed' => (bool) ($onboarding['whatsapp_completed'] ?? false),
            'current_step' => $this->resolveCurrentStep($tenant),
            'completed_at' => $onboarding['completed_at'] ?? null,
            'provider' => $this->connectionProvider->key(),
            'company' => [
                'name' => $tenant->name,
                'document' => $tenant->document,
                'email' => $tenant->email,
                'phone' => $tenant->phone,
                'domain' => $tenant->domain,
            ],
            'whatsapp' => [
                'connected' => $tenant->isWhatsappConnected(),
                'phone_number' => $tenant->whatsappSetting('phone_number'),
                // SEC-03: não expor connection_id/session_id na API de status.
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function completeCompany(Tenant $tenant, array $data): array
    {
        if (! filled($data['domain'] ?? null)) {
            unset($data['domain']);
        }

        $this->tenants->update($tenant, $data);
        $tenant->refresh();

        $tenant->mergeOnboardingSettings([
            'company_completed' => true,
            'current_step' => $tenant->isWhatsappConnected() ? 'finish' : 'whatsapp',
        ]);

        return $this->status($tenant->fresh());
    }

    public function initializeWhatsApp(Tenant $tenant): ConnectionInitializationDTO
    {
        return $this->connectionProvider->initialize($tenant);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function completeWhatsApp(Tenant $tenant, array $payload): array
    {
        $result = $this->connectionProvider->complete($tenant, $payload);

        if (! $result->connected) {
            throw ValidationException::withMessages([
                'whatsapp' => [$result->message ?? 'Falha ao conectar WhatsApp.'],
            ]);
        }

        $tenant->mergeWhatsappSettings(array_filter(
            $result->settings,
            fn (mixed $value): bool => $value !== null,
        ));

        $tenant->mergeOnboardingSettings([
            'whatsapp_completed' => true,
            'current_step' => 'finish',
        ]);

        if ($user = request()->user()) {
            $this->audit->record($user, AuditAction::WhatsAppConnected, $tenant);
        }

        return [
            ...$this->status($tenant->fresh()),
            'connection_message' => $result->message,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function finish(Tenant $tenant): array
    {
        $onboarding = $tenant->onboardingSettings();

        if (! ($onboarding['company_completed'] ?? false)) {
            throw ValidationException::withMessages([
                'company' => ['Conclua a configuração da empresa antes de finalizar.'],
            ]);
        }

        $whatsappReady = (bool) ($onboarding['whatsapp_completed'] ?? false)
            || $tenant->isWhatsappConnected();

        $tenant->mergeOnboardingSettings([
            'company_completed' => true,
            'whatsapp_completed' => $whatsappReady,
            'current_step' => 'done',
            'completed_at' => now()->toIso8601String(),
        ]);

        if ($user = request()->user()) {
            $this->audit->record($user, AuditAction::OnboardingFinished, $tenant);
        }

        return $this->status($tenant->fresh());
    }

    private function resolveCurrentStep(Tenant $tenant): string
    {
        if ($tenant->isOnboardingCompleted()) {
            return 'done';
        }

        $onboarding = $tenant->onboardingSettings();

        if (! ($onboarding['company_completed'] ?? false)) {
            return 'company';
        }

        if (! ($onboarding['whatsapp_completed'] ?? false) && ! $tenant->isWhatsappConnected()) {
            return 'whatsapp';
        }

        return 'finish';
    }
}
