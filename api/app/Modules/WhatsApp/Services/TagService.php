<?php

namespace App\Modules\WhatsApp\Services;

use App\Modules\WhatsApp\Models\WhatsAppTag;
use Illuminate\Database\Eloquent\Collection;

class TagService
{
    public function list(): Collection
    {
        return WhatsAppTag::query()->orderBy('sort_order')->orderBy('name')->get();
    }

    public function create(array $data): WhatsAppTag
    {
        return WhatsAppTag::query()->create($data);
    }

    public function update(WhatsAppTag $tag, array $data): WhatsAppTag
    {
        $tag->fill($data)->save();

        return $tag->fresh();
    }

    public function delete(WhatsAppTag $tag): void
    {
        $tag->delete();
    }
}
