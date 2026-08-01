<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplicationShellTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_app_uses_the_xsrf_cookie_instead_of_a_meta_token(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/');

        $response->assertOk();
        $response->assertCookie('XSRF-TOKEN');
        $response->assertDontSee('name="csrf-token"', false);
    }

    public function test_unauthenticated_home_page_also_sets_the_xsrf_cookie(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertCookie('XSRF-TOKEN');
    }
}
