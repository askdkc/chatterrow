<?php

namespace App\Http\Controllers;

use App\Models\Server;
use App\Models\StoredFile;
use App\Support\OnlyOfficeConfigService;
use App\Support\OnlyOfficeDocumentTypeResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class OnlyOfficePreviewController extends Controller
{
    public function __construct(
        private OnlyOfficeConfigService $configService,
        private OnlyOfficeDocumentTypeResolver $documentTypeResolver,
    ) {}

    public function __invoke(Server $server, StoredFile $storedFile): JsonResponse
    {
        Gate::authorize('view', $server);
        abort_unless($storedFile->server_id === $server->id, 404);

        if (! (bool) config('onlyoffice.enabled', false)) {
            return response()->json(['message' => 'OnlyOffice preview is unavailable.'], 404);
        }

        if (! $this->configService->isConfigured()) {
            return response()->json(['message' => 'OnlyOffice preview is unavailable.'], 503);
        }

        abort_unless($this->documentTypeResolver->resolve($storedFile) !== null, 422);
        abort_unless(Storage::disk($storedFile->disk)->exists($storedFile->path), 404);

        if (! $this->configService->isDocumentServerAvailable()) {
            return response()->json([
                'message' => 'OnlyOffice preview is unavailable.',
                'code' => 'document_server_unavailable',
            ], 503);
        }

        return response()
            ->json([
                'documentServerUrl' => (string) config('onlyoffice.public_url'),
                'config' => $this->configService->make($storedFile),
            ])
            ->header('Cache-Control', 'private, no-store');
    }
}
