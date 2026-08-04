<?php

namespace App\Support;

use App\Models\Message;
use App\Models\MessageMention;
use Illuminate\Support\Str;

final class MessagePayload
{
    public function __construct(private MessageMentionParser $parser) {}

    /** @return array<string, mixed> */
    public function make(Message $message): array
    {
        $message->loadMissing([
            'user:id,name,email',
            'attachments',
            'mentions.user:id,name',
            'reactions.user:id,name',
        ]);

        $tokens = $this->parser->tokens((string) $message->body);
        $mentions = $message->mentions
            ->filter(fn (MessageMention $mention): bool => $mention->user !== null)
            ->map(fn (MessageMention $mention): array => [
                'id' => $mention->user->id,
                'name' => $mention->user->name,
                'kind' => $mention->kind,
            ])
            ->values();

        if (! $message->is_reminder
            && $message->user !== null
            && collect($tokens)->contains(fn (array $token): bool => $token['kind'] === 'direct'
                && $token['id'] === (string) $message->user->id)
            && ! $mentions->contains(fn (array $mention): bool => $mention['id'] === $message->user->id)) {
            $mentions = $mentions
                ->push([
                    'id' => $message->user->id,
                    'name' => $message->user->name,
                    'kind' => 'direct',
                ])
                ->sortBy('id')
                ->values();
        }

        $payload = $message->toArray();
        $resolvedIds = $mentions->mapWithKeys(fn (array $mention): array => [
            (string) $mention['id'] => true,
        ])->all();

        $payload['body'] = $this->parser->replaceTokens(
            (string) $message->body,
            static function (array $token) use ($resolvedIds): string {
                if ($token['kind'] === 'everyone' || isset($resolvedIds[$token['id'] ?? ''])) {
                    return $token['raw'];
                }

                return '[deleted user]';
            },
        );
        $payload['mentions'] = $mentions->all();
        $payload['reactions'] = $message->reactions
            ->groupBy('emoji')
            ->map(fn ($reactions, string $emoji): array => [
                'emoji' => $emoji,
                'count' => $reactions->count(),
                'user_ids' => $reactions->pluck('user_id')->values()->all(),
                'user_names' => $reactions->pluck('user.name')->filter()->values()->all(),
            ])
            ->values()
            ->all();

        return $payload;
    }

    public function excerpt(Message $message, int $limit = 240): string
    {
        $message->loadMissing([
            'user:id,name',
            'mentions.user:id,name',
        ]);

        $names = $message->mentions
            ->filter(fn (MessageMention $mention): bool => $mention->user !== null)
            ->mapWithKeys(fn (MessageMention $mention): array => [
                (string) $mention->user_id => $mention->user->name,
            ])
            ->all();

        if (! $message->is_reminder && $message->user !== null) {
            foreach ($this->parser->tokens((string) $message->body) as $token) {
                if ($token['kind'] === 'direct'
                    && $token['id'] === (string) $message->user->id) {
                    $names[(string) $message->user->id] = $message->user->name;
                }
            }
        }

        $displayBody = $this->parser->replaceTokens(
            (string) $message->body,
            static function (array $token) use ($names): string {
                if ($token['kind'] === 'everyone') {
                    return '@everyone';
                }

                return isset($names[$token['id'] ?? ''])
                    ? '@'.$names[$token['id']]
                    : '[deleted user]';
            },
        );
        $displayBody = preg_replace('/<@[^>\r\n]*>/', '[deleted user]', $displayBody) ?? $displayBody;

        $excerpt = strip_tags($displayBody);
        $excerpt = preg_replace('/\s+/u', ' ', trim($excerpt)) ?? trim($excerpt);

        return Str::limit($excerpt, $limit);
    }
}
