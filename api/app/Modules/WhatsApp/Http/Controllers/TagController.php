<?php

namespace App\Modules\WhatsApp\Http\Controllers;

use App\Modules\Shared\Http\Controllers\ApiController;
use App\Modules\WhatsApp\Http\Requests\StoreTagRequest;
use App\Modules\WhatsApp\Http\Requests\UpdateTagRequest;
use App\Modules\WhatsApp\Http\Resources\TagResource;
use App\Modules\WhatsApp\Models\WhatsAppTag;
use App\Modules\WhatsApp\Services\TagService;
use Illuminate\Http\JsonResponse;

class TagController extends ApiController
{
    public function __construct(
        private readonly TagService $service,
    ) {}

    public function index(): JsonResponse
    {
        $this->authorize('viewAny', WhatsAppTag::class);

        return $this->success(TagResource::collection($this->service->list()));
    }

    public function store(StoreTagRequest $request): JsonResponse
    {
        $this->authorize('create', WhatsAppTag::class);

        return $this->created(
            TagResource::make($this->service->create($request->validated())),
            'Tag criada com sucesso.',
        );
    }

    public function show(WhatsAppTag $tag): JsonResponse
    {
        $this->authorize('view', $tag);

        return $this->success(TagResource::make($tag));
    }

    public function update(UpdateTagRequest $request, WhatsAppTag $tag): JsonResponse
    {
        $this->authorize('update', $tag);

        return $this->success(
            TagResource::make($this->service->update($tag, $request->validated())),
            'Tag atualizada com sucesso.',
        );
    }

    public function destroy(WhatsAppTag $tag): JsonResponse
    {
        $this->authorize('delete', $tag);

        $this->service->delete($tag);

        return $this->success(null, 'Tag removida com sucesso.');
    }
}
