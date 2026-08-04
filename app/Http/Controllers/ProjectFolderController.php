<?php

namespace App\Http\Controllers;

use App\Models\ProjectFolder;
use App\Models\Server;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProjectFolderController extends Controller
{
    private const DEFAULT_COLOR = '#5865F2';

    public function store(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:80',
                Rule::unique('project_folders')->where('user_id', $user->id),
            ],
            'color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'icon' => $this->iconRules(),
        ]);

        $nextPosition = (int) ProjectFolder::query()
            ->where('user_id', $user->id)
            ->max('position') + 1;

        $iconPath = $this->storeIcon($request, $user);

        try {
            $folder = ProjectFolder::create([
                'user_id' => $user->id,
                'name' => $validated['name'],
                'color' => strtoupper($validated['color'] ?? self::DEFAULT_COLOR),
                'icon_path' => $iconPath,
                'position' => $nextPosition,
            ]);
        } catch (\Throwable $exception) {
            if ($iconPath !== null) {
                Storage::disk('local')->delete($iconPath);
            }

            throw $exception;
        }

        return response()->json(['folder' => $folder], 201);
    }

    public function update(Request $request, ProjectFolder $projectFolder): JsonResponse
    {
        Gate::authorize('update', $projectFolder);

        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:80',
                Rule::unique('project_folders')
                    ->where('user_id', $user->id)
                    ->ignore($projectFolder->id),
            ],
            'color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'icon' => $this->iconRules(),
            'remove_icon' => ['sometimes', 'boolean'],
        ]);

        $oldIconPath = $projectFolder->icon_path;
        $newIconPath = $this->storeIcon($request, $user);
        $removeIcon = (bool) ($validated['remove_icon'] ?? false);
        $nextIconPath = $newIconPath ?? ($removeIcon ? null : $oldIconPath);

        try {
            $projectFolder->update([
                'name' => $validated['name'],
                'color' => strtoupper($validated['color'] ?? $projectFolder->color),
                'icon_path' => $nextIconPath,
            ]);
        } catch (\Throwable $exception) {
            if ($newIconPath !== null) {
                Storage::disk('local')->delete($newIconPath);
            }

            throw $exception;
        }

        if ($oldIconPath !== null && $oldIconPath !== $nextIconPath) {
            Storage::disk('local')->delete($oldIconPath);
        }

        return response()->json(['folder' => $projectFolder->fresh()]);
    }

    public function icon(ProjectFolder $projectFolder): StreamedResponse
    {
        Gate::authorize('update', $projectFolder);

        abort_unless($projectFolder->icon_path !== null, 404);

        $disk = Storage::disk('local');
        abort_unless($disk->exists($projectFolder->icon_path), 404);

        $extension = strtolower(pathinfo($projectFolder->icon_path, PATHINFO_EXTENSION));
        $contentType = match ($extension) {
            'gif' => 'image/gif',
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            default => 'image/png',
        };

        return $disk->response($projectFolder->icon_path, null, [
            'Content-Type' => $contentType,
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function destroy(ProjectFolder $projectFolder): JsonResponse
    {
        Gate::authorize('delete', $projectFolder);

        $projectFolder->delete();

        return response()->json(['ok' => true]);
    }

    public function assign(Request $request, Server $server): JsonResponse
    {
        Gate::authorize('view', $server);

        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'project_folder_id' => [
                'nullable',
                'integer',
                Rule::exists('project_folders', 'id')
                    ->where('user_id', $user->id),
            ],
        ]);

        DB::table('server_user')
            ->where('server_id', $server->id)
            ->where('user_id', $user->id)
            ->update([
                'project_folder_id' => $validated['project_folder_id'] ?? null,
                'updated_at' => now(),
            ]);

        return response()->json([
            'server_id' => $server->id,
            'project_folder_id' => $validated['project_folder_id'] ?? null,
        ]);
    }

    /** @return array<int, string> */
    private function iconRules(): array
    {
        return [
            'nullable',
            'image',
            'mimes:png,jpg,jpeg,gif,webp',
            'max:1024',
            'dimensions:min_width=16,min_height=16,max_width=512,max_height=512',
        ];
    }

    private function storeIcon(Request $request, User $user): ?string
    {
        $icon = $request->file('icon');

        if ($icon === null) {
            return null;
        }

        $extension = strtolower($icon->guessExtension() ?: 'png');
        $directory = sprintf(
            'project-folder-icons/%d/%s',
            $user->id,
            now()->format('Y/m/d'),
        );
        $path = $icon->storeAs(
            $directory,
            Str::uuid()->toString().'.'.$extension,
            'local',
        );

        if ($path === false) {
            throw new RuntimeException('Project folder icon storage failed.');
        }

        return $path;
    }
}
