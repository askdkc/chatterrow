<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class Localization
{
    /**
     * Apply the locale saved by BreezeJP's language switcher.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $configuredLocales = config('app.supported_locales', []);
        $supportedLocales = is_array($configuredLocales)
            ? array_keys($configuredLocales)
            : [];
        $requestedLocale = $request->session()->get('locale');
        $defaultLocale = (string) config('app.locale', 'en');
        $fallbackLocale = (string) config('app.fallback_locale', 'en');

        $locale = $this->resolveLocale(
            is_string($requestedLocale) && in_array($requestedLocale, $supportedLocales, true)
                ? $requestedLocale
                : null,
            $defaultLocale,
            $fallbackLocale,
            $supportedLocales,
        );

        App::setLocale($locale);

        if ($requestedLocale !== null && $requestedLocale !== $locale) {
            $request->session()->put('locale', $locale);
        }

        return $next($request);
    }

    /**
     * Resolve a configured locale to one that has a usable catalog.
     * English intentionally has no JSON catalog because Laravel falls back
     * to the translation key itself for the default language.
     *
     * @param  list<string>  $supportedLocales
     */
    private function resolveLocale(
        ?string $requestedLocale,
        string $defaultLocale,
        string $fallbackLocale,
        array $supportedLocales,
    ): string {
        foreach (array_unique([$requestedLocale, $defaultLocale, $fallbackLocale, 'en']) as $locale) {
            if (is_string($locale)
                && in_array($locale, $supportedLocales, true)
                && ($locale === 'en' || is_file(lang_path($locale.'.json')))) {
                return $locale;
            }
        }

        return $supportedLocales[0] ?? 'en';
    }
}
