<?php

namespace App\Actions;

use App\Models\Channel;
use App\Models\Message;
use App\Models\MessageMention;
use App\Models\Server;
use App\Models\StoredFile;
use App\Models\User;
use App\Support\MessageMentionParser;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class MessageMutation
{
    public function __construct(private MessageMentionParser $parser) {}

    /**
     * @param  array<int, array<string, mixed>>  $attachments
     */
    public function create(
        Server $server,
        Channel $channel,
        User $author,
        string $body,
        ?int $parentId = null,
        array $attachments = [],
    ): MessageMutationResult {
        return DB::transaction(function () use ($server, $channel, $author, $body, $parentId, $attachments): MessageMutationResult {
            $this->ensureChannelScope($server, $channel);
            $this->ensureCurrentMember($server, $author);
            $this->ensureValidParent($server, $channel, $parentId);

            $targets = $this->resolveTargets($server, $author, $body);
            $message = Message::query()->create([
                'server_id' => $server->id,
                'channel_id' => $channel->id,
                'user_id' => $author->id,
                'parent_id' => $parentId,
                'body' => $body,
            ]);

            $newMentions = $this->syncMentions($message, $targets);
            $this->attachFiles($server, $message, $attachments);
            $this->loadMessage($message);

            return new MessageMutationResult($message, $newMentions);
        });
    }

    public function update(
        Server $server,
        Channel $channel,
        Message $message,
        User $author,
        string $body,
    ): MessageMutationResult {
        return DB::transaction(function () use ($server, $channel, $message, $author, $body): MessageMutationResult {
            $this->ensureChannelScope($server, $channel);

            if ($message->server_id !== $server->id || $message->channel_id !== $channel->id) {
                throw (new ModelNotFoundException)->setModel(Message::class, [$message->id]);
            }

            $tokens = $message->is_reminder ? [] : $this->parser->tokens($body);

            if ($tokens !== []) {
                $this->ensureCurrentMember($server, $author);
            }

            $targets = $message->is_reminder
                ? []
                : $this->resolveTargets($server, $author, $body, $tokens);

            $message->update(['body' => $body]);
            $newMentions = $this->syncMentions($message, $targets);
            $this->loadMessage($message);

            return new MessageMutationResult($message, $newMentions);
        });
    }

    /**
     * @param  list<array{kind: 'direct'|'everyone', id: string|null, raw: string, offset: int, length: int}>|null  $tokens
     * @return array<int, string>
     */
    private function resolveTargets(
        Server $server,
        User $author,
        string $body,
        ?array $tokens = null,
    ): array {
        $tokens ??= $this->parser->tokens($body);

        if ($tokens === []) {
            return [];
        }

        $directIds = [];
        $everyone = false;

        foreach ($tokens as $token) {
            if ($token['kind'] === 'everyone') {
                $everyone = true;

                continue;
            }

            if ($token['id'] === null || preg_match('/\A[1-9][0-9]*\z/', $token['id']) !== 1) {
                throw ValidationException::withMessages([
                    'body' => ['The message contains an invalid user mention.'],
                ]);
            }

            $directIds[$token['id']] = true;
        }

        $members = $server->members()
            ->orderBy('users.id')
            ->get(['users.id']);
        $memberIds = array_fill_keys(array_map('strval', $members->modelKeys()), true);

        foreach (array_keys($directIds) as $directId) {
            if (! isset($memberIds[$directId])) {
                throw ValidationException::withMessages([
                    'body' => ['The selected user is not a member of this server.'],
                ]);
            }
        }

        $targets = [];

        if ($everyone) {
            foreach ($members as $member) {
                if ((int) $member->id !== $author->id) {
                    $targets[$member->id] = 'everyone';
                }
            }
        }

        foreach (array_keys($directIds) as $directId) {
            $userId = (int) $directId;

            if ($userId !== $author->id) {
                // Direct mentions win when a user is also covered by everyone.
                $targets[$userId] = 'direct';
            }
        }

        return $targets;
    }

    /**
     * @param  array<int, string>  $targets
     * @return Collection<int, MessageMention>
     */
    private function syncMentions(Message $message, array $targets): Collection
    {
        $existing = $message->mentions()->get()->keyBy('user_id');
        $targetIds = array_keys($targets);

        if ($targetIds === []) {
            $message->mentions()->delete();

            return new Collection;
        }

        $message->mentions()
            ->whereNotIn('user_id', $targetIds)
            ->delete();

        $newMentions = new Collection;

        foreach ($targets as $userId => $kind) {
            /** @var MessageMention|null $mention */
            $mention = $existing->get($userId);

            if ($mention !== null) {
                if ($mention->kind !== $kind) {
                    $mention->update(['kind' => $kind]);
                }

                continue;
            }

            $newMentions->push($message->mentions()->create([
                'user_id' => $userId,
                'kind' => $kind,
            ]));
        }

        return $newMentions;
    }

    /**
     * @param  array<int, array<string, mixed>>  $attachments
     */
    private function attachFiles(Server $server, Message $message, array $attachments): void
    {
        foreach ($attachments as $attachment) {
            $storedFile = StoredFile::query()
                ->where('server_id', $server->id)
                ->where('path', $attachment['path'])
                ->first();

            if ($storedFile !== null && $storedFile->attachable_id === null) {
                $storedFile->update([
                    'attachable_type' => Message::class,
                    'attachable_id' => $message->id,
                    'original_name' => $attachment['original_name'],
                    'mime_type' => $attachment['mime_type'] ?? $storedFile->mime_type,
                    'size' => $attachment['size'] ?? $storedFile->size,
                ]);
            }
        }
    }

    private function ensureChannelScope(Server $server, Channel $channel): void
    {
        if ($channel->server_id !== $server->id) {
            throw (new ModelNotFoundException)->setModel(Channel::class, [$channel->id]);
        }
    }

    private function ensureCurrentMember(Server $server, User $author): void
    {
        $isMember = DB::table('server_user')
            ->where('server_id', $server->id)
            ->where('user_id', $author->id)
            ->lockForUpdate()
            ->exists();

        if (! $isMember) {
            throw new AuthorizationException('The user is no longer a member of this server.');
        }
    }

    private function ensureValidParent(Server $server, Channel $channel, ?int $parentId): void
    {
        if ($parentId === null) {
            return;
        }

        $valid = Message::query()
            ->whereKey($parentId)
            ->where('server_id', $server->id)
            ->where('channel_id', $channel->id)
            ->whereNull('parent_id')
            ->exists();

        if (! $valid) {
            throw ValidationException::withMessages([
                'parent_id' => ['The selected parent message is invalid.'],
            ]);
        }
    }

    private function loadMessage(Message $message): void
    {
        $message->load([
            'user:id,name,email',
            'attachments',
            'mentions.user:id,name',
        ]);
    }
}
