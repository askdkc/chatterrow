<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

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

        return [
            ...parent::share($request),
            'translations' => fn (): array => is_file(lang_path(app()->getLocale().'.json'))
                ? json_decode((string) file_get_contents(lang_path(app()->getLocale().'.json')), true, 512, JSON_THROW_ON_ERROR)
                : [],
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
                'servers' => $user
                    ? $user->servers()->orderBy('servers.name')->get(['servers.id', 'servers.name'])
                    : [],
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
