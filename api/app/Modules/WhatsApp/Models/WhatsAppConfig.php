<?php

namespace App\Modules\WhatsApp\Models;

use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WhatsAppConfig extends Model
{
    use BelongsToTenant;
    use SoftDeletes;

    protected $table = 'whatsapp_configs';

    protected $fillable = [
        'name',
        'waba_id',
        'phone_number_id',
        'access_token',
        'verify_token',
        'is_active',
        'webhook_url',
        'last_connected_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_connected_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
