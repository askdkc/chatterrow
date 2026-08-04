<?php

namespace Tests\Feature;

use App\Models\Server;
use App\Models\ServerInvitation;
use App\Models\User;
use App\Notifications\ProjectInvitationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProjectInvitationTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $invitee;

    private Server $server;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->invitee = User::factory()->create();
        $this->server = Server::factory()->create([
            'created_by' => $this->owner->id,
            'name' => 'Project Alpha',
        ]);
        $this->server->members()->attach($this->owner->id);
    }

    public function test_registered_user_can_accept_an_invitation_from_the_project_index(): void
    {
        Notification::fake();

        $invitationId = $this->actingAs($this->owner)
            ->postJson(route('servers.invitations.store', $this->server), [
                'email' => $this->invitee->email,
            ])
            ->assertCreated()
            ->json('invitation.id');

        Notification::assertNothingSent();

        $this->actingAs($this->invitee)
            ->get(route('servers.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('servers/Index')
                ->has('servers', 0)
                ->where('invitations.0.id', $invitationId)
                ->where('invitations.0.server.name', 'Project Alpha')
                ->where('invitations.0.inviter.id', $this->owner->id));

        $this->actingAs($this->invitee)
            ->patchJson(route('project-invitations.accept', $invitationId))
            ->assertOk()
            ->assertJsonPath('server.id', $this->server->id);

        $this->assertDatabaseHas('server_user', [
            'server_id' => $this->server->id,
            'user_id' => $this->invitee->id,
            'role' => Server::ROLE_MEMBER,
        ]);
        $this->assertDatabaseHas('server_invitations', [
            'id' => $invitationId,
            'user_id' => $this->invitee->id,
            'status' => ServerInvitation::STATUS_ACCEPTED,
        ]);
    }

    public function test_declined_invitation_is_visible_to_owner_and_can_be_resent_or_deleted(): void
    {
        Notification::fake();

        $invitationId = $this->actingAs($this->owner)
            ->postJson(route('servers.invitations.store', $this->server), [
                'email' => $this->invitee->email,
            ])
            ->json('invitation.id');

        $this->actingAs($this->invitee)
            ->patchJson(route('project-invitations.decline', $invitationId))
            ->assertOk();

        $this->actingAs($this->owner)
            ->getJson(route('servers.invitations.index', $this->server))
            ->assertOk()
            ->assertJsonPath('invitations.0.id', $invitationId)
            ->assertJsonPath('invitations.0.status', ServerInvitation::STATUS_DECLINED);

        $this->actingAs($this->owner)
            ->postJson(route('servers.invitations.resend', [$this->server, $invitationId]))
            ->assertOk()
            ->assertJsonPath('invitation.status', ServerInvitation::STATUS_PENDING);

        Notification::assertSentOnDemand(ProjectInvitationNotification::class, function (
            ProjectInvitationNotification $notification,
            array $channels,
            AnonymousNotifiable $notifiable,
        ): bool {
            return $notification->hasAccount
                && $channels === ['mail']
                && $notifiable->routes['mail'] === $this->invitee->email;
        });

        $this->actingAs($this->owner)
            ->deleteJson(route('servers.invitations.destroy', [$this->server, $invitationId]))
            ->assertOk();

        $this->assertDatabaseMissing('server_invitations', ['id' => $invitationId]);
    }

    public function test_unregistered_email_receives_a_registration_link_and_registration_claims_invitation(): void
    {
        Notification::fake();
        $email = 'new-person@example.com';
        $plainToken = null;

        $invitationId = $this->actingAs($this->owner)
            ->postJson(route('servers.invitations.store', $this->server), [
                'email' => $email,
            ])
            ->assertCreated()
            ->assertJsonPath('delivery', 'email')
            ->json('invitation.id');

        Notification::assertSentOnDemand(ProjectInvitationNotification::class, function (
            ProjectInvitationNotification $notification,
            array $channels,
            AnonymousNotifiable $notifiable,
        ) use ($email, &$plainToken): bool {
            $plainToken = $notification->plainToken;

            return ! $notification->hasAccount
                && $channels === ['mail']
                && $notifiable->routes['mail'] === $email;
        });
        $this->assertIsString($plainToken);

        $this->post(route('logout'));

        $this->get(route('register', ['invitation' => $plainToken]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/Register')
                ->where('invitation.email', $email)
                ->where('invitation.server_name', 'Project Alpha'));

        $this->post(route('register.store'), [
            'name' => 'New Person',
            'email' => $email,
            'password' => 'password',
            'password_confirmation' => 'password',
            'invitation' => $plainToken,
        ])->assertRedirect(route('dashboard', absolute: false));

        $newUser = User::query()->where('email', $email)->firstOrFail();
        $this->assertAuthenticatedAs($newUser);
        $this->assertDatabaseHas('server_invitations', [
            'id' => $invitationId,
            'user_id' => $newUser->id,
            'status' => ServerInvitation::STATUS_PENDING,
        ]);
        $this->assertDatabaseMissing('server_user', [
            'server_id' => $this->server->id,
            'user_id' => $newUser->id,
        ]);
    }

    public function test_other_users_and_non_owners_cannot_manage_or_answer_an_invitation(): void
    {
        $invitation = ServerInvitation::query()->create([
            'server_id' => $this->server->id,
            'invited_by' => $this->owner->id,
            'user_id' => $this->invitee->id,
            'email' => $this->invitee->email,
            'token_hash' => hash('sha256', str_repeat('a', 64)),
            'status' => ServerInvitation::STATUS_PENDING,
            'sent_at' => now(),
        ]);
        $outsider = User::factory()->create();

        $this->actingAs($this->invitee)
            ->getJson(route('servers.invitations.index', $this->server))
            ->assertForbidden();
        $this->actingAs($this->invitee)
            ->postJson(route('servers.invitations.resend', [$this->server, $invitation]))
            ->assertForbidden();
        $this->actingAs($outsider)
            ->patchJson(route('project-invitations.accept', $invitation))
            ->assertNotFound();

        $this->assertDatabaseMissing('server_user', [
            'server_id' => $this->server->id,
            'user_id' => $outsider->id,
        ]);
    }
}
