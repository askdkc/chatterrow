<?php

namespace App\Http\Controllers;

use App\Models\Server;
use App\Models\ServerInvitation;
use App\Models\User;
use App\Notifications\ProjectInvitationNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class ServerInvitationController extends Controller
{
    public function index(Server $server): JsonResponse
    {
        Gate::authorize('viewInvitations', $server);

        $invitations = $server->invitations()
            ->whereIn('status', [
                ServerInvitation::STATUS_PENDING,
                ServerInvitation::STATUS_DECLINED,
            ])
            ->with('user:id,name,email')
            ->latest('sent_at')
            ->get()
            ->map(fn (ServerInvitation $invitation): array => $this->payload($invitation));

        return response()->json(['invitations' => $invitations]);
    }

    public function store(Request $request, Server $server): JsonResponse
    {
        Gate::authorize('manageMembers', $server);

        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);
        $email = mb_strtolower(trim($validated['email']));
        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        if ($user !== null && $server->members()->whereKey($user->id)->exists()) {
            return response()->json([
                'message' => 'このユーザーはすでにプロジェクトへ参加しています。',
            ], 422);
        }

        $existing = $server->invitations()->where('email', $email)->first();

        if ($existing?->status === ServerInvitation::STATUS_PENDING) {
            return response()->json([
                'message' => 'このメールアドレスにはすでに招待を送信しています。',
            ], 409);
        }

        if ($existing?->status === ServerInvitation::STATUS_DECLINED) {
            return response()->json([
                'message' => 'この招待は拒否されています。招待一覧から再送してください。',
            ], 409);
        }

        $plainToken = Str::random(64);
        $invitation = $existing ?? new ServerInvitation;
        $invitation->fill([
            'server_id' => $server->id,
            'invited_by' => $request->user()->id,
            'user_id' => $user?->id,
            'email' => $email,
            'token_hash' => hash('sha256', $plainToken),
            'status' => ServerInvitation::STATUS_PENDING,
            'sent_at' => now(),
            'responded_at' => null,
        ])->save();

        if ($user === null) {
            $this->sendMail($invitation, $plainToken, false);
        }

        return response()->json([
            'invitation' => $this->payload($invitation->load('user:id,name,email')),
            'delivery' => $user === null ? 'email' : 'in_app',
        ], 201);
    }

    public function resend(Request $request, Server $server, ServerInvitation $invitation): JsonResponse
    {
        Gate::authorize('manageMembers', $server);
        $this->ensureBelongsToServer($server, $invitation);

        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [mb_strtolower($invitation->email)])
            ->first();

        if ($user !== null && $server->members()->whereKey($user->id)->exists()) {
            return response()->json([
                'message' => 'このユーザーはすでにプロジェクトへ参加しています。',
            ], 422);
        }

        $plainToken = Str::random(64);
        $invitation->update([
            'invited_by' => $request->user()->id,
            'user_id' => $user?->id,
            'token_hash' => hash('sha256', $plainToken),
            'status' => ServerInvitation::STATUS_PENDING,
            'sent_at' => now(),
            'responded_at' => null,
        ]);

        $this->sendMail($invitation, $plainToken, $user !== null);

        return response()->json([
            'invitation' => $this->payload($invitation->load('user:id,name,email')),
        ]);
    }

    public function destroy(Server $server, ServerInvitation $invitation): JsonResponse
    {
        Gate::authorize('manageMembers', $server);
        $this->ensureBelongsToServer($server, $invitation);

        $invitation->delete();

        return response()->json(['ok' => true]);
    }

    public function accept(Request $request, ServerInvitation $invitation): JsonResponse
    {
        $user = $request->user();
        abort_unless($invitation->isFor($user), 404);

        if ($invitation->server->archived_at !== null) {
            return response()->json([
                'message' => 'このプロジェクトはアーカイブされています。',
            ], 409);
        }

        $server = DB::transaction(function () use ($invitation, $user): Server {
            $lockedInvitation = ServerInvitation::query()
                ->lockForUpdate()
                ->findOrFail($invitation->id);

            abort_unless($lockedInvitation->isFor($user), 404);

            if ($lockedInvitation->status !== ServerInvitation::STATUS_PENDING) {
                abort(409, 'この招待にはすでに回答しています。');
            }

            if ($lockedInvitation->server->members()->whereKey($user->id)->doesntExist()) {
                $lockedInvitation->server->members()->attach($user->id, [
                    'role' => Server::ROLE_MEMBER,
                ]);
            }
            $lockedInvitation->update([
                'user_id' => $user->id,
                'status' => ServerInvitation::STATUS_ACCEPTED,
                'responded_at' => now(),
            ]);

            return $lockedInvitation->server;
        });

        return response()->json([
            'server' => $server->loadCount(['channels', 'members']),
        ]);
    }

    public function decline(Request $request, ServerInvitation $invitation): JsonResponse
    {
        $user = $request->user();
        abort_unless($invitation->isFor($user), 404);

        DB::transaction(function () use ($invitation, $user): void {
            $lockedInvitation = ServerInvitation::query()
                ->lockForUpdate()
                ->findOrFail($invitation->id);

            abort_unless($lockedInvitation->isFor($user), 404);

            if ($lockedInvitation->status !== ServerInvitation::STATUS_PENDING) {
                abort(409, 'この招待にはすでに回答しています。');
            }

            $lockedInvitation->update([
                'user_id' => $user->id,
                'status' => ServerInvitation::STATUS_DECLINED,
                'responded_at' => now(),
            ]);
        });

        return response()->json(['ok' => true]);
    }

    private function ensureBelongsToServer(Server $server, ServerInvitation $invitation): void
    {
        abort_unless($invitation->server_id === $server->id, 404);
    }

    /** @return array<string, mixed> */
    private function payload(ServerInvitation $invitation): array
    {
        return [
            'id' => $invitation->id,
            'email' => $invitation->email,
            'status' => $invitation->status,
            'sent_at' => $invitation->sent_at,
            'responded_at' => $invitation->responded_at,
            'registered' => $invitation->user_id !== null,
            'user' => $invitation->user?->only(['id', 'name', 'email']),
        ];
    }

    private function sendMail(ServerInvitation $invitation, string $plainToken, bool $hasAccount): void
    {
        Notification::route('mail', $invitation->email)
            ->notify(new ProjectInvitationNotification($invitation, $plainToken, $hasAccount));
    }
}
