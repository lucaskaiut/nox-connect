<?php

namespace App\Modules\WhatsApp\Contracts;

use App\Modules\Tenant\Models\Tenant;
use App\Modules\WhatsApp\DTOs\ConnectionInitializationDTO;
use App\Modules\WhatsApp\DTOs\ConnectionResultDTO;
use App\Modules\WhatsApp\DTOs\ConnectionStatusDTO;

/**
 * Porta de onboarding/conexão WhatsApp.
 * Independente do provedor (SDK, OAuth, formulário, redirect).
 */
interface WhatsAppConnectionProvider
{
    public function key(): string;

    /**
     * Configuração pública para o frontend iniciar o fluxo.
     */
    public function getConfiguration(): array;

    /**
     * Prepara a conexão do tenant (ex.: payload do SDK).
     */
    public function initialize(Tenant $tenant): ConnectionInitializationDTO;

    /**
     * Finaliza a conexão com o payload retornado pelo frontend/provider.
     *
     * @param  array<string, mixed>  $payload
     */
    public function complete(Tenant $tenant, array $payload): ConnectionResultDTO;

    public function status(Tenant $tenant): ConnectionStatusDTO;
}
