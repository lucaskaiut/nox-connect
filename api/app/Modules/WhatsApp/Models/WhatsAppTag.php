<?php

namespace App\Modules\WhatsApp\Models;

use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WhatsAppTag extends Model
{
    use BelongsToTenant;
    use SoftDeletes;

    protected $table = 'whatsapp_tags';

    protected $fillable = [
        'tenant_id',
        'name',
        'color',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }
}
