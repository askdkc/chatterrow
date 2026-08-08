<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use JsonException;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $localeNames = config('app.supported_locales', []);
        $localeNames = is_array($localeNames) ? $localeNames : [];
        $servers = $user
            ? $user->servers()
                ->active()
                ->orderBy('servers.name')
                ->get(['servers.id', 'servers.name', 'servers.icon_path'])
                ->map(fn ($server): array => [
                    'id' => $server->id,
                    'name' => $server->name,
                    'icon_url' => $server->icon_url,
                    'project_folder_id' => $server->pivot->project_folder_id,
                ])
            : [];

        return [
            ...parent::share($request),
            'locale' => app()->getLocale(),
            'locales' => array_keys($localeNames),
            'localeNames' => $localeNames,
            'translations' => fn (): array => $this->translations(app()->getLocale()),
            'name' => in_array(app()->getLocale(), ['ja', 'zh_CN', 'zh_TW'], true)
                ? '茶多楼'
                : 'Chatterrow',
            'auth' => [
                'user' => $user,
                'servers' => $servers,
                'folders' => $user
                    ? $user->projectFolders()
                        ->orderBy('position')
                        ->orderBy('name')
                        ->get(['id', 'name', 'color', 'icon_path', 'position'])
                    : [],
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * Load the JSON catalog used by the Svelte runtime translator.
     *
     * @return array<string, string>
     */
    private function translations(string $locale): array
    {
        $path = lang_path($locale.'.json');

        if (! is_file($path)) {
            return [];
        }

        try {
            $translations = json_decode(
                (string) file_get_contents($path),
                true,
                512,
                JSON_THROW_ON_ERROR,
            );
        } catch (JsonException) {
            return [];
        }

        return is_array($translations) ? $translations : [];
    }
}
