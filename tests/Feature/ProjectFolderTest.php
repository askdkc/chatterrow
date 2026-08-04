<?php

namespace Tests\Feature;

use App\Models\ProjectFolder;
use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProjectFolderTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_only_the_signed_in_users_folders_and_assignments(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $ownerFolder = ProjectFolder::create([
            'user_id' => $owner->id,
            'name' => 'Owner folder',
            'position' => 1,
        ]);
        $memberFolder = ProjectFolder::create([
            'user_id' => $member->id,
            'name' => 'Member folder',
            'position' => 1,
        ]);
        $server = Server::factory()->create(['created_by' => $owner->id]);
        $server->members()->attach($owner->id, [
            'role' => Server::ROLE_ADMIN,
            'project_folder_id' => $ownerFolder->id,
        ]);
        $server->members()->attach($member->id, [
            'role' => Server::ROLE_MEMBER,
            'project_folder_id' => $memberFolder->id,
        ]);

        $this->actingAs($member)
            ->get(route('servers.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('servers/Index')
                ->has('folders', 1)
                ->where('folders.0.id', $memberFolder->id)
                ->has('auth.folders', 1)
                ->where('auth.folders.0.id', $memberFolder->id)
                ->where('auth.folders.0.color', '#5865F2')
                ->where('auth.folders.0.icon_url', null)
                ->where('auth.servers.0.project_folder_id', $memberFolder->id)
                ->where('servers.0.id', $server->id)
                ->where('servers.0.project_folder_id', $memberFolder->id));
    }

    public function test_user_can_create_rename_and_delete_their_folder_without_deleting_projects(): void
    {
        $user = User::factory()->create();
        $server = Server::factory()->create(['created_by' => $user->id]);
        $server->members()->attach($user->id, ['role' => Server::ROLE_ADMIN]);

        $folderId = $this->actingAs($user)
            ->postJson(route('project-folders.store'), ['name' => 'Client work'])
            ->assertCreated()
            ->json('folder.id');

        $this->patchJson(route('servers.folder.assign', $server), [
            'project_folder_id' => $folderId,
        ])->assertOk();

        $this->patchJson(route('project-folders.update', $folderId), [
            'name' => 'Renamed folder',
        ])->assertOk()->assertJsonPath('folder.name', 'Renamed folder');

        $this->deleteJson(route('project-folders.destroy', $folderId))
            ->assertOk();

        $this->assertDatabaseHas('servers', ['id' => $server->id]);
        $this->assertDatabaseHas('server_user', [
            'server_id' => $server->id,
            'user_id' => $user->id,
            'project_folder_id' => null,
        ]);
    }

    public function test_folder_names_are_unique_per_user_but_not_globally(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();

        $this->actingAs($first)
            ->postJson(route('project-folders.store'), ['name' => 'Shared name'])
            ->assertCreated();

        $this->postJson(route('project-folders.store'), ['name' => 'Shared name'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');

        $this->actingAs($second)
            ->postJson(route('project-folders.store'), ['name' => 'Shared name'])
            ->assertCreated();
    }

    public function test_user_can_set_serve_and_remove_a_folder_icon_and_color(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $other = User::factory()->create();
        $icon = UploadedFile::fake()->image('engineering.png', 64, 64)->size(100);

        $response = $this->actingAs($user)
            ->post(route('project-folders.store'), [
                'name' => 'Engineering',
                'color' => '#ff5500',
                'icon' => $icon,
            ])
            ->assertCreated()
            ->assertJsonPath('folder.color', '#FF5500');

        $folder = ProjectFolder::query()->findOrFail($response->json('folder.id'));
        $iconPath = $folder->icon_path;

        $this->assertNotNull($iconPath);
        Storage::disk('local')->assertExists($iconPath);
        $response->assertJsonPath(
            'folder.icon_url',
            route('project-folders.icon', $folder).'?v='.substr(sha1($iconPath), 0, 12),
        );
        $response->assertJsonMissingPath('folder.icon_path');

        $this->get(route('project-folders.icon', $folder))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->actingAs($other)
            ->get(route('project-folders.icon', $folder))
            ->assertForbidden();

        $this->actingAs($user)
            ->patchJson(route('project-folders.update', $folder), [
                'name' => 'Engineering',
                'color' => '#22aa77',
                'remove_icon' => true,
            ])
            ->assertOk()
            ->assertJsonPath('folder.color', '#22AA77')
            ->assertJsonPath('folder.icon_url', null);

        Storage::disk('local')->assertMissing($iconPath);
    }

    public function test_folder_appearance_rejects_unsafe_or_invalid_values(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('project-folders.store'), [
                'name' => 'Unsafe',
                'color' => 'red; background: url(javascript:alert(1))',
                'icon' => UploadedFile::fake()->create(
                    'unsafe.svg',
                    10,
                    'image/svg+xml',
                ),
            ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['color', 'icon']);
    }

    public function test_user_cannot_manage_another_users_folder(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $folder = ProjectFolder::create([
            'user_id' => $owner->id,
            'name' => 'Private organization',
            'position' => 1,
        ]);

        $this->actingAs($other)
            ->patchJson(route('project-folders.update', $folder), [
                'name' => 'Hijacked',
            ])
            ->assertForbidden();

        $this->deleteJson(route('project-folders.destroy', $folder))
            ->assertForbidden();

        $this->assertDatabaseHas('project_folders', [
            'id' => $folder->id,
            'name' => 'Private organization',
        ]);
    }

    public function test_user_can_only_assign_visible_projects_to_their_own_folder(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $outsider = User::factory()->create();
        $memberFolder = ProjectFolder::create([
            'user_id' => $member->id,
            'name' => 'Member folder',
            'position' => 1,
        ]);
        $ownerFolder = ProjectFolder::create([
            'user_id' => $owner->id,
            'name' => 'Owner folder',
            'position' => 1,
        ]);
        $server = Server::factory()->create(['created_by' => $owner->id]);
        $server->members()->attach($owner->id, ['role' => Server::ROLE_ADMIN]);
        $server->members()->attach($member->id, ['role' => Server::ROLE_MEMBER]);

        $this->actingAs($member)
            ->patchJson(route('servers.folder.assign', $server), [
                'project_folder_id' => $memberFolder->id,
            ])
            ->assertOk();

        $this->assertDatabaseHas('server_user', [
            'server_id' => $server->id,
            'user_id' => $member->id,
            'project_folder_id' => $memberFolder->id,
        ]);
        $this->assertDatabaseHas('server_user', [
            'server_id' => $server->id,
            'user_id' => $owner->id,
            'project_folder_id' => null,
        ]);

        $this->patchJson(route('servers.folder.assign', $server), [
            'project_folder_id' => $ownerFolder->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('project_folder_id');

        $this->actingAs($outsider)
            ->patchJson(route('servers.folder.assign', $server), [
                'project_folder_id' => null,
            ])
            ->assertForbidden();
    }

    public function test_archived_projects_are_counted_on_the_index_and_listed_on_their_own_page(): void
    {
        $user = User::factory()->create();
        $active = Server::factory()->create(['created_by' => $user->id]);
        $archived = Server::factory()->create([
            'created_by' => $user->id,
            'archived_at' => now(),
        ]);
        $active->members()->attach($user->id, ['role' => Server::ROLE_ADMIN]);
        $archived->members()->attach($user->id, ['role' => Server::ROLE_ADMIN]);

        $this->actingAs($user)
            ->get(route('servers.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('servers/Index')
                ->has('servers', 1)
                ->where('servers.0.id', $active->id)
                ->where('archivedCount', 1));

        $this->get(route('servers.archived'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('servers/Archived')
                ->has('servers', 1)
                ->where('servers.0.id', $archived->id));
    }
}
