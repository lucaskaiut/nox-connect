<?php

namespace App\Modules\WhatsApp\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppWebhookLog extends Model
{
    protected $table = 'whatsapp_webhook_logs';

    protected $fillable = [
        'whatsapp_config_id',
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
            'response_status' => 'integer',
            'duration_ms' => 'integer',
        ];
    }

    public function config(): BelongsTo
    {
        return $this->belongsTo(WhatsAppConfig::class, 'whatsapp_config_id');
    }
}
