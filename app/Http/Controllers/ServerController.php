<?php

namespace App\Http\Controllers;

use App\Models\ProjectFolder;
use App\Models\Server;
use App\Models\ServerInvitation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ServerController extends Controller
{
    private const ICON_MAX_DIMENSION = 512;

    private const ICON_MAX_INPUT_DIMENSION = 8192;

    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $servers = Server::query()
            ->visibleTo($user)
            ->active()
            ->select('servers.*')
            ->addSelect([
                'project_folder_id' => DB::table('server_user')
                    ->select('project_folder_id')
                    ->whereColumn('server_id', 'servers.id')
                    ->where('user_id', $user->id)
                    ->limit(1),
            ])
            ->with('members:id,name,email')
            ->withCount(['channels', 'members'])
            ->orderBy('name')
            ->get();

        $folders = ProjectFolder::query()
            ->where('user_id', $user->id)
            ->orderBy('position')
            ->orderBy('name')
            ->get(['id', 'name', 'color', 'icon_path', 'position']);

        $invitations = ServerInvitation::query()
            ->forUser($user)
            ->where('status', ServerInvitation::STATUS_PENDING)
            ->whereHas('server', function ($serverQuery) use ($user): void {
                $serverQuery
                    ->whereNull('archived_at')
                    ->whereDoesntHave(
                        'members',
                        fn ($memberQuery) => $memberQuery->whereKey($user->id),
                    );
            })
            ->with([
                'server:id,created_by,name,description',
                'inviter:id,name,email',
            ])
            ->latest('sent_at')
            ->get();

        return Inertia::render('servers/Index', [
            'servers' => $servers,
            'folders' => $folders,
            'archivedCount' => Server::query()
                ->visibleTo($user)
                ->whereNotNull('archived_at')
                ->count(),
            'invitations' => $invitations,
        ]);
    }

    public function archived(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $servers = Server::query()
            ->visibleTo($user)
            ->whereNotNull('archived_at')
            ->with('members:id,name,email')
            ->withCount(['channels', 'members'])
            ->orderByDesc('archived_at')
            ->orderBy('name')
            ->get();

        return Inertia::render('servers/Archived', [
            'servers' => $servers,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', Server::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:255'],
            'starts_on' => ['nullable', 'date_format:Y-m-d'],
            'ends_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            'icon' => $this->iconRules(),
        ]);

        /** @var User $user */
        $user = $request->user();

        unset($validated['icon']);
        $iconPath = null;

        try {
            $server = DB::transaction(function () use ($request, $validated, $user, &$iconPath): Server {
                $server = Server::create([
                    ...$validated,
                    'created_by' => $user->id,
                ]);

                $server->members()->attach($user->id, [
                    'role' => Server::ROLE_ADMIN,
                ]);

                $iconPath = $this->storeIcon($request, $server);

                if ($iconPath !== null) {
                    $server->update(['icon_path' => $iconPath]);
                }

                return $server;
            });
        } catch (\Throwable $exception) {
            if ($iconPath !== null) {
                Storage::disk('local')->delete($iconPath);
            }

            throw $exception;
        }

        return response()->json(['server' => $server->fresh()], 201);
    }

    public function show(Server $server): Response
    {
        Gate::authorize('view', $server);

        $server->load([
            'channels' => fn ($query) => $query->orderBy('name'),
            'members:id,name,email',
        ]);

        return Inertia::render('servers/Show', [
            'server' => $server,
            'members' => $server->members,
        ]);
    }

    public function icon(Server $server): StreamedResponse
    {
        Gate::authorize('view', $server);

        abort_unless($server->icon_path !== null, 404);

        $disk = Storage::disk('local');
        abort_unless($disk->exists($server->icon_path), 404);

        $extension = strtolower(pathinfo($server->icon_path, PATHINFO_EXTENSION));
        $contentType = match ($extension) {
            'gif' => 'image/gif',
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            default => 'image/png',
        };

        return $disk->response($server->icon_path, null, [
            'Content-Type' => $contentType,
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function update(Request $request, Server $server): JsonResponse|RedirectResponse
    {
        Gate::authorize('update', $server);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:255'],
            'starts_on' => ['nullable', 'date_format:Y-m-d'],
            'ends_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            'icon' => $this->iconRules(),
            'remove_icon' => ['sometimes', 'boolean'],
        ]);

        unset($validated['icon'], $validated['remove_icon']);

        Validator::make([
            'starts_on' => array_key_exists('starts_on', $request->all())
                ? $request->input('starts_on')
                : $server->starts_on?->toDateString(),
            'ends_on' => array_key_exists('ends_on', $request->all())
                ? $request->input('ends_on')
                : $server->ends_on?->toDateString(),
        ], [
            'starts_on' => ['nullable', 'date_format:Y-m-d'],
            'ends_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
        ])->validate();

        $oldIconPath = $server->icon_path;
        $newIconPath = $this->storeIcon($request, $server);
        $removeIcon = $request->boolean('remove_icon');
        $nextIconPath = $newIconPath ?? ($removeIcon ? null : $oldIconPath);

        try {
            $server->update([
                ...$validated,
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

        if ($request->expectsJson()) {
            return response()->json(['server' => $server->fresh()]);
        }

        return back();
    }

    public function destroy(Request $request, Server $server): JsonResponse|RedirectResponse
    {
        Gate::authorize('delete', $server);

        DB::transaction(function () use ($server): void {
            foreach ($server->storedFiles()->get() as $storedFile) {
                $disk = Storage::disk($storedFile->disk);

                if ($disk->exists($storedFile->path) && ! $disk->delete($storedFile->path)) {
                    throw new RuntimeException('Project file deletion failed.');
                }

                $storedFile->delete();
            }

            $server->delete();
        });

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return redirect()->route('servers.index');
    }

    public function archive(Server $server): JsonResponse
    {
        Gate::authorize('archive', $server);

        $server->update(['archived_at' => now()]);

        return response()->json(['server' => $server->fresh()]);
    }

    public function restore(Server $server): JsonResponse
    {
        Gate::authorize('restore', $server);

        $server->update(['archived_at' => null]);

        return response()->json(['server' => $server->fresh()]);
    }

    public function destroyMember(Server $server, User $user): JsonResponse
    {
        Gate::authorize('manageMembers', $server);

        if ($server->created_by === $user->id) {
            return response()->json([
                'message' => __('The project administrator cannot be removed from the project.'),
            ], 422);
        }

        $result = DB::transaction(function () use ($server, $user): string {
            $membership = DB::table('server_user')
                ->where('server_id', $server->id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first(['id', 'role']);

            if ($membership === null) {
                return 'deleted';
            }

            if ($membership->role === Server::ROLE_ADMIN) {
                $hasPermanentOwner = $server->created_by !== null
                    && DB::table('server_user')
                        ->where('server_id', $server->id)
                        ->where('user_id', $server->created_by)
                        ->exists();
                $adminCount = DB::table('server_user')
                    ->where('server_id', $server->id)
                    ->where('role', Server::ROLE_ADMIN)
                    ->count();

                if (! $hasPermanentOwner && $adminCount <= 1) {
                    return 'last_admin';
                }
            }

            if ($server->todos()->where('assignee_id', $user->id)->exists()) {
                return 'assigned';
            }

            DB::table('server_user')->where('id', $membership->id)->delete();

            return 'deleted';
        });

        if ($result === 'assigned') {
            return response()->json([
                'message' => __('Members with assigned tasks cannot be removed. Reassign their tasks first.'),
            ], 409);
        }

        if ($result === 'last_admin') {
            return response()->json([
                'message' => __('The last administrator cannot be removed from the project.'),
            ], 422);
        }

        return response()->json(['ok' => true]);
    }

    public function updateMemberRole(Request $request, Server $server, User $user): JsonResponse
    {
        Gate::authorize('manageMembers', $server);

        $validated = $request->validate([
            'role' => ['required', Rule::in([
                Server::ROLE_ADMIN,
                Server::ROLE_MEMBER,
            ])],
        ]);

        if ($server->created_by === $user->id && $validated['role'] !== Server::ROLE_ADMIN) {
            return response()->json([
                'message' => __('The project creator\'s administrator role cannot be removed.'),
            ], 422);
        }

        $updated = DB::transaction(function () use ($server, $user, $validated): bool {
            $membership = DB::table('server_user')
                ->where('server_id', $server->id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first(['id', 'role']);

            if ($membership === null) {
                return false;
            }

            if ($membership->role === Server::ROLE_ADMIN
                && $validated['role'] === Server::ROLE_MEMBER) {
                $hasPermanentOwner = $server->created_by !== null
                    && DB::table('server_user')
                        ->where('server_id', $server->id)
                        ->where('user_id', $server->created_by)
                        ->exists();
                $adminCount = DB::table('server_user')
                    ->where('server_id', $server->id)
                    ->where('role', Server::ROLE_ADMIN)
                    ->count();

                if (! $hasPermanentOwner && $adminCount <= 1) {
                    abort(422, __('The last administrator cannot be changed to a regular member.'));
                }
            }

            DB::table('server_user')
                ->where('id', $membership->id)
                ->update([
                    'role' => $validated['role'],
                    'updated_at' => now(),
                ]);

            return true;
        });

        if (! $updated) {
            return response()->json([
                'message' => __('The selected user is not a member of the project.'),
            ], 404);
        }

        $member = $server->members()
            ->whereKey($user->id)
            ->firstOrFail();

        return response()->json(['user' => $member]);
    }

    /** @return array<int, string> */
    private function iconRules(): array
    {
        return [
            'nullable',
            'image',
            'mimes:png,jpg,jpeg,gif,webp',
            'max:1024',
            sprintf(
                'dimensions:min_width=16,min_height=16,max_width=%d,max_height=%d',
                self::ICON_MAX_INPUT_DIMENSION,
                self::ICON_MAX_INPUT_DIMENSION,
            ),
        ];
    }

    private function storeIcon(Request $request, Server $server): ?string
    {
        $icon = $request->file('icon');

        if ($icon === null) {
            return null;
        }

        $extension = strtolower($icon->guessExtension() ?: 'png');
        $directory = sprintf(
            'project-icons/%d/%s',
            $server->id,
            now()->format('Y/m/d'),
        );
        $filename = Str::uuid()->toString().'.'.$extension;
        $sourcePath = $icon->getRealPath();

        if (! is_string($sourcePath) || ! is_file($sourcePath)) {
            throw new RuntimeException('Project icon source is unavailable.');
        }

        $dimensions = getimagesize($sourcePath);

        if ($dimensions === false) {
            throw new RuntimeException('Project icon dimensions could not be read.');
        }

        if ($dimensions[0] > self::ICON_MAX_DIMENSION || $dimensions[1] > self::ICON_MAX_DIMENSION) {
            return $this->storeResizedIcon($sourcePath, $directory, $filename, $extension);
        }

        $path = $icon->storeAs(
            $directory,
            $filename,
            'local',
        );

        if ($path === false) {
            throw new RuntimeException('Project icon storage failed.');
        }

        return $path;
    }

    private function storeResizedIcon(
        string $sourcePath,
        string $directory,
        string $filename,
        string $extension,
    ): string {
        $workDirectory = sys_get_temp_dir().'/project-icon-'.Str::uuid()->toString();

        if (! File::makeDirectory($workDirectory, 0700, true)) {
            throw new RuntimeException('Project icon work directory could not be created.');
        }

        try {
            $outputPath = $workDirectory.'/resized.'.$extension;
            $command = [
                config('services.imagemagick.path', 'convert'),
                '-limit',
                'memory',
                '128MiB',
                '-limit',
                'map',
                '256MiB',
                '-limit',
                'disk',
                '1GiB',
            ];

            if (in_array($extension, ['jpg', 'jpeg'], true)) {
                $command = [
                    ...$command,
                    '-define',
                    'jpeg:size=1024x1024',
                ];
            }

            $command = [
                ...$command,
                $sourcePath,
                '-auto-orient',
                '-thumbnail',
                self::ICON_MAX_DIMENSION.'x'.self::ICON_MAX_DIMENSION.'>',
                '-strip',
                $outputPath,
            ];
            $result = Process::timeout((int) config('services.imagemagick.timeout', 45))->run($command);

            if (! $result->successful() || ! is_file($outputPath)) {
                Log::warning('Project icon ImageMagick process failed.', [
                    'exit_code' => $result->exitCode(),
                    'extension' => $extension,
                    'error' => Str::limit(trim($result->errorOutput()), 2000),
                ]);

                throw new RuntimeException('Project icon resizing failed.');
            }

            $dimensions = getimagesize($outputPath);

            if ($dimensions === false
                || $dimensions[0] > self::ICON_MAX_DIMENSION
                || $dimensions[1] > self::ICON_MAX_DIMENSION) {
                throw new RuntimeException('Resized project icon is invalid.');
            }

            $contents = file_get_contents($outputPath);
            $path = $directory.'/'.$filename;

            if ($contents === false || ! Storage::disk('local')->put($path, $contents)) {
                throw new RuntimeException('Project icon storage failed.');
            }

            return $path;
        } finally {
            File::deleteDirectory($workDirectory);
        }
    }
}
