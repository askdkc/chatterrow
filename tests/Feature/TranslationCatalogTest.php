<?php

namespace Tests\Feature;

use JsonException;
use Tests\TestCase;

class TranslationCatalogTest extends TestCase
{
    /**
     * @var list<string>
     */
    private const CATALOGS = [
        'fr',
        'de',
        'zh_CN',
        'zh_TW',
        'es',
        'pt_BR',
        'pt_PT',
        'ko',
    ];

    /**
     * @var array<string, array<string, string>>
     */
    private const REPRESENTATIVE_TRANSLATIONS = [
        'fr' => ['Log in' => 'Se connecter'],
        'de' => ['Log in' => 'Anmelden'],
        'zh_CN' => ['Log in' => '登录'],
        'zh_TW' => ['Log in' => '登入'],
        'es' => ['Log in' => 'Iniciar sesión'],
        'pt_BR' => ['Log in' => 'Entrar'],
        'pt_PT' => ['Log in' => 'Iniciar sessão'],
        'ko' => ['Log in' => '로그인'],
    ];

    public function test_additional_catalogs_match_the_japanese_keys_and_placeholders(): void
    {
        $japanese = $this->readCatalog('ja');

        foreach (self::CATALOGS as $locale) {
            $catalog = $this->readCatalog($locale);

            $this->assertSame(
                array_keys($japanese),
                array_keys($catalog),
                "Catalog keys differ for {$locale}.",
            );

            foreach ($japanese as $key => $translation) {
                $this->assertNotSame('', $catalog[$key], "Empty translation for {$locale}: {$key}");
                $this->assertSame(
                    $this->placeholders($translation),
                    $this->placeholders($catalog[$key]),
                    "Placeholder mismatch for {$locale}: {$key}",
                );
            }

            foreach (self::REPRESENTATIVE_TRANSLATIONS[$locale] as $key => $translation) {
                $this->assertSame($translation, $catalog[$key]);
            }
        }
    }

    public function test_framework_language_files_exist_for_each_supported_locale(): void
    {
        foreach (array_keys(config('app.supported_locales')) as $locale) {
            foreach (['auth', 'pagination', 'passwords', 'validation'] as $group) {
                $this->assertFileExists(
                    lang_path("{$locale}/{$group}.php"),
                    "Missing {$group} catalog for {$locale}.",
                );
            }
        }
    }

    public function test_framework_messages_are_translated_for_supported_locales(): void
    {
        $frameworkMessages = [
            'auth.failed',
            'auth.password',
            'auth.throttle',
            'passwords.reset',
            'passwords.sent',
            'passwords.throttled',
            'passwords.token',
            'passwords.user',
            'pagination.previous',
            'pagination.next',
            'validation.after_or_equal',
            'validation.array',
            'validation.boolean',
            'validation.confirmed',
            'validation.current_password',
            'validation.date',
            'validation.date_format',
            'validation.email',
            'validation.exists',
            'validation.file',
            'validation.image',
            'validation.in',
            'validation.integer',
            'validation.max.string',
            'validation.mimes',
            'validation.password.letters',
            'validation.password.mixed',
            'validation.password.numbers',
            'validation.password.symbols',
            'validation.password.uncompromised',
            'validation.regex',
            'validation.required',
            'validation.required_without',
            'validation.size.string',
            'validation.string',
            'validation.timezone',
            'validation.unique',
            'validation.uploaded',
        ];

        foreach (array_merge(['en', 'ja'], self::CATALOGS) as $locale) {
            app()->setLocale($locale);

            foreach ($frameworkMessages as $key) {
                $this->assertNotSame($key, trans($key), "Missing {$locale} translation: {$key}");
            }
        }

        app()->setLocale('en');
    }

    /**
     * @return array<string, string>
     *
     * @throws JsonException
     */
    private function readCatalog(string $locale): array
    {
        $catalog = json_decode(
            (string) file_get_contents(lang_path($locale.'.json')),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $this->assertIsArray($catalog);

        return $catalog;
    }

    /**
     * @return list<string>
     */
    private function placeholders(string $translation): array
    {
        preg_match_all('/:([A-Za-z_][A-Za-z0-9_]*)/', $translation, $matches);
        $placeholders = $matches[1] ?? [];
        sort($placeholders);

        return $placeholders;
    }
}
