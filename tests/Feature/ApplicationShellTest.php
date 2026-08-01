<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplicationShellTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_app_html_contains_one_csrf_meta_tag(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/');

        $response->assertOk();
        $response->assertSee('<meta name="csrf-token"', false);
        $response->assertSee('content="', false);

        // Exactly one CSRF meta element with a non-empty token.
        $html = $response->getContent();
        $this->assertSame(1, substr_count($html, 'name="csrf-token"'));
        preg_match('/<meta name="csrf-token" content="([^"]+)"/', $html, $m);
        $this->assertNotEmpty($m[1] ?? '');
    }

    public function test_unauthenticated_home_page_also_exposes_csrf_meta(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('name="csrf-token"', false);
    }
}
