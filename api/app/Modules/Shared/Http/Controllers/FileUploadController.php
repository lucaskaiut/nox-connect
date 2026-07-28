<?php

namespace App\Modules\Shared\Http\Controllers;

use App\Modules\Shared\Http\Controllers\ApiController;
use App\Modules\Tenant\Support\Facades\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class FileUploadController extends ApiController
{
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:jpeg,jpg,png,gif,webp', 'max:10240'],
        ]);

        $tenantId = TenantContext::tenantId();
        $file = $request->file('file');
        $path = $file->store("uploads/{$tenantId}", 'local');

        try {
            $url = Storage::disk('local')->temporaryUrl($path, now()->addHour());
        } catch (RuntimeException) {
            Storage::disk('local')->delete($path);
            $path = $file->store("uploads/{$tenantId}", 'public');
            $url = Storage::disk('public')->url($path);
        }

        return $this->created([
            'url' => $url,
            'path' => $path,
        ]);
    }
}
