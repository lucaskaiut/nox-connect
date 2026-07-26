<?php

namespace App\Modules\WhatsApp\Models;

use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class WhatsAppWebhookLog extends Model
{
    use BelongsToTenant;

    protected $table = 'whatsapp_webhook_logs';

    protected $fillable = [
        'tenant_id',
        'method',
        'url',
        'request_headers',
        'request_payload',
        'response_status',
        'response_body',
        'error_message',
        'duration_ms',
    ];

    protected function casts(): array
    {
        return [
            'request_headers' => 'array',
            'request_payload' => 'array',
        ];
    }
}
