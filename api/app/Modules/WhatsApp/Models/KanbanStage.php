<?php

namespace App\Modules\WhatsApp\Models;

use App\Modules\Tenant\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class KanbanStage extends Model
{
    use BelongsToTenant;
    use SoftDeletes;

    protected $table = 'whatsapp_kanban_stages';

    protected $fillable = [
        'name',
        'color',
        'sort_order',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_default' => 'boolean',
        ];
    }
}
