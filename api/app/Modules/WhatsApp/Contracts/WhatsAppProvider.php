<?php

namespace App\Modules\WhatsApp\Contracts;

use App\Modules\Tenant\Models\Tenant;
use App\Modules\WhatsApp\DTOs\ConnectionResultDTO;
use App\Modules\WhatsApp\DTOs\ConnectionStatusDTO;
use App\Modules\WhatsApp\DTOs\MessageResultDTO;
use App\Modules\WhatsApp\DTOs\SendAudioDTO;
use App\Modules\WhatsApp\DTOs\SendDocumentDTO;
use App\Modules\WhatsApp\DTOs\SendImageDTO;
use App\Modules\WhatsApp\DTOs\SendTemplateDTO;
use App\Modules\WhatsApp\DTOs\SendTextMessageDTO;
use App\Modules\WhatsApp\DTOs\SendVideoDTO;

/**
 * Porta de saída para o provedor WhatsApp ativo da aplicação.
 * Credenciais globais vêm de config(); identificadores por tenant vêm de Tenant settings.
 */
interface WhatsAppProvider
{
    public function key(): string;

    /**
     * Onboarding: cria/vincula a conexão do tenant no provedor.
     *
     * @param  array<string, mixed>  $input  Dados de onboarding (sem secrets globais)
     */
    public function createConnection(Tenant $tenant, array $input = []): ConnectionResultDTO;

    public function disconnectConnection(Tenant $tenant): void;

    public function getConnectionStatus(Tenant $tenant): ConnectionStatusDTO;

    public function sendText(SendTextMessageDTO $dto): MessageResultDTO;

    public function sendImage(SendImageDTO $dto): MessageResultDTO;

    public function sendDocument(SendDocumentDTO $dto): MessageResultDTO;

    public function sendAudio(SendAudioDTO $dto): MessageResultDTO;

    public function sendVideo(SendVideoDTO $dto): MessageResultDTO;

    public function sendTemplate(SendTemplateDTO $dto): MessageResultDTO;

    public function uploadMedia(Tenant $tenant, string $filePath, string $mimeType): ?string;

    public function markAsRead(Tenant $tenant, string $externalMessageId): void;
}
