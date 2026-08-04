<?php

namespace Tests\Feature;

use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ServerIconTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_administrator_can_set_serve_replace_and_remove_an_icon(): void
    {
        Storage::fake('local');

        $owner = User::factory()->create();
        $member = User::factory()->create();
        $outsider = User::factory()->create();
        $server = Server::factory()->create(['created_by' => $owner->id]);
        $server->members()->attach([
            $owner->id => ['role' => Server::ROLE_ADMIN],
            $member->id => ['role' => Server::ROLE_MEMBER],
        ]);

        $response = $this->actingAs($owner)
            ->post(route('servers.update', $server), [
                '_method' => 'PATCH',
                'name' => $server->name,
                'icon' => UploadedFile::fake()->image('first.png', 64, 64)->size(100),
            ], ['Accept' => 'application/json'])
            ->assertOk();

        $server->refresh();
        $firstIconPath = $server->icon_path;

        $this->assertNotNull($firstIconPath);
        Storage::disk('local')->assertExists($firstIconPath);
        $response->assertJsonPath(
            'server.icon_url',
            route('servers.icon', $server).'?v='.substr(sha1($firstIconPath), 0, 12),
        );
        $response->assertJsonMissingPath('server.icon_path');

        $this->actingAs($member)
            ->get(route('servers.icon', $server))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->actingAs($outsider)
            ->get(route('servers.icon', $server))
            ->assertForbidden();

        $replacement = $this->actingAs($owner)
            ->post(route('servers.update', $server), [
                '_method' => 'PATCH',
                'name' => $server->name,
                'icon' => UploadedFile::fake()->image('replacement.webp', 96, 96)->size(120),
            ], ['Accept' => 'application/json'])
            ->assertOk();

        $server->refresh();
        $replacementIconPath = $server->icon_path;

        $this->assertNotNull($replacementIconPath);
        $this->assertNotSame($firstIconPath, $replacementIconPath);
        Storage::disk('local')->assertMissing($firstIconPath);
        Storage::disk('local')->assertExists($replacementIconPath);
        $replacement->assertJsonPath(
            'server.icon_url',
            route('servers.icon', $server).'?v='.substr(sha1($replacementIconPath), 0, 12),
        );

        $this->post(route('servers.update', $server), [
            '_method' => 'PATCH',
            'name' => $server->name,
            'remove_icon' => '1',
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('server.icon_url', null);

        Storage::disk('local')->assertMissing($replacementIconPath);
        $this->assertNull($server->fresh()->icon_path);
    }

    public function test_project_can_be_created_with_an_icon_and_shared_props_include_it(): void
    {
        Storage::fake('local');

        $owner = User::factory()->create();

        $response = $this->actingAs($owner)
            ->post(route('servers.store'), [
                'name' => 'Icon project',
                'icon' => UploadedFile::fake()->image('project.jpg', 80, 80)->size(100),
            ], ['Accept' => 'application/json'])
            ->assertCreated();

        $server = Server::query()->findOrFail($response->json('server.id'));
        $iconPath = $server->icon_path;

        $this->assertNotNull($iconPath);
        Storage::disk('local')->assertExists($iconPath);
        $response->assertJsonPath('server.icon_url', route('servers.icon', $server).'?v='.substr(sha1($iconPath), 0, 12));

        $this->get(route('servers.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('servers.0.icon_url', route('servers.icon', $server).'?v='.substr(sha1($iconPath), 0, 12))
                ->where('auth.servers.0.icon_url', route('servers.icon', $server).'?v='.substr(sha1($iconPath), 0, 12)));
    }

    public function test_large_project_icon_is_resized_to_fit_within_512_pixels(): void
    {
        Storage::fake('local');

        $owner = User::factory()->create();
        $server = Server::factory()->create(['created_by' => $owner->id]);
        $server->members()->attach($owner->id, ['role' => Server::ROLE_ADMIN]);

        $this->actingAs($owner)
            ->post(route('servers.update', $server), [
                '_method' => 'PATCH',
                'name' => $server->name,
                'icon' => UploadedFile::fake()->image('large.jpg', 6000, 4000)->size(100),
            ], ['Accept' => 'application/json'])
            ->assertOk();

        $iconPath = $server->fresh()->icon_path;

        $this->assertNotNull($iconPath);
        Storage::disk('local')->assertExists($iconPath);

        $dimensions = getimagesize(Storage::disk('local')->path($iconPath));

        $this->assertIsArray($dimensions);
        $this->assertSame(512, $dimensions[0]);
        $this->assertSame(341, $dimensions[1]);
    }

    public function test_project_icon_rejects_unsafe_and_invalid_files(): void
    {
        Storage::fake('local');

        $owner = User::factory()->create();
        $server = Server::factory()->create(['created_by' => $owner->id]);
        $server->members()->attach($owner->id, ['role' => Server::ROLE_ADMIN]);

        $this->actingAs($owner)
            ->post(route('servers.update', $server), [
                '_method' => 'PATCH',
                'name' => $server->name,
                'icon' => UploadedFile::fake()->create(
                    'unsafe.svg',
                    10,
                    'image/svg+xml',
                ),
            ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('icon');

        $this->assertNull($server->fresh()->icon_path);
    }
}
