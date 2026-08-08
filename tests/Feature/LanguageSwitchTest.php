<?php

namespace Tests\Feature;

use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LanguageSwitchTest extends TestCase
{
    public function test_supported_locale_is_saved_in_the_session(): void
    {
        $this->from('/login')
            ->get(route('language.switch', 'fr'))
            ->assertRedirect('/login')
            ->assertSessionHas('locale', 'fr');
    }

    public function test_unsupported_locale_is_rejected_without_changing_the_session(): void
    {
        $this->withSession(['locale' => 'ja'])
            ->get('/language/not-supported')
            ->assertNotFound()
            ->assertSessionHas('locale', 'ja');
    }

    public function test_session_locale_is_applied_to_the_next_request(): void
    {
        $this->withSession(['locale' => 'de'])
            ->get(route('home'))
            ->assertOk();

        $this->assertSame('de', app()->getLocale());
    }

    public function test_inertia_shares_locale_names_and_the_selected_catalog(): void
    {
        $this->withSession(['locale' => 'fr'])
            ->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Welcome')
                ->where('locale', 'fr')
                ->where('name', 'Chatterrow')
                ->where('locales', array_keys(config('app.supported_locales')))
                ->where('localeNames.fr', 'Français')
                ->where('translations.Log in', 'Se connecter'));
    }

    public function test_cjk_locales_keep_the_chinese_application_name(): void
    {
        foreach (['ja', 'zh_CN', 'zh_TW'] as $locale) {
            $this->withSession(['locale' => $locale])
                ->get(route('home'))
                ->assertInertia(fn (Assert $page) => $page->where('name', '茶多楼'));
        }
    }

    public function test_root_html_lang_reflects_the_selected_locale(): void
    {
        $this->withSession(['locale' => 'zh_CN'])
            ->get(route('home'))
            ->assertSee('<html lang="zh-CN"', false);
    }

    public function test_missing_catalog_falls_back_to_the_configured_fallback_locale(): void
    {
        config([
            'app.locale' => 'missing',
            'app.fallback_locale' => 'fr',
            'app.supported_locales' => [
                'missing' => 'Missing',
                'fr' => 'Français',
                'en' => 'English',
            ],
        ]);

        $this->withSession(['locale' => 'missing'])
            ->get(route('home'))
            ->assertOk();

        $this->assertSame('fr', app()->getLocale());
    }
}
