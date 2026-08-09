<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;

final class GlobalSearchService
{
    public const RESULT_LIMIT = 50;

    public function __construct(private MarkdownSearchIndex $markdown) {}

    /**
     * @return array{results: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    public function search(User $user, string $value, int $limit = self::RESULT_LIMIT, ?int $channelId = null): array
    {
        $query = SearchQuery::from($value);
        $limit = min(max($limit, 1), self::RESULT_LIMIT);

        if ($query->isEmpty()) {
            return [
                'results' => [],
                'meta' => $this->meta($query, 0, false, $limit),
            ];
        }

        $this->markdown->ensureAvailable();
        $candidateLimit = $limit + 1;
        $messages = collect($this->searchMessages($user, $query, $candidateLimit, $channelId));
        $files = $this->markdown
            ->searchForUser($user, $query->value, $candidateLimit, $channelId)
            ->map(fn (\stdClass $row): array => $this->fileResult($row));
        $candidates = $messages->concat($files)->sort(
            static function (array $left, array $right): int {
                $dateComparison = (strtotime((string) ($right['created_at'] ?? '')) ?: 0)
                    <=> (strtotime((string) ($left['created_at'] ?? '')) ?: 0);

                if ($dateComparison !== 0) {
                    return $dateComparison;
                }

                $typeComparison = strcmp((string) $left['type'], (string) $right['type']);

                return $typeComparison !== 0
                    ? $typeComparison
                    : ((int) $right['id'] <=> (int) $left['id']);
            },
        )->values();
        $hasMore = $candidates->count() > $limit;

        return [
            'results' => array_values($candidates->take($limit)->all()),
            'meta' => $this->meta($query, min($candidates->count(), $limit), $hasMore, $limit),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function searchMessages(User $user, SearchQuery $query, int $limit, ?int $channelId): array
    {
        $driver = DB::connection()->getDriverName();
        $builder = DB::table('messages')
            ->join('channels', function ($join): void {
                $join->on('channels.id', '=', 'messages.channel_id')
                    ->whereColumn('channels.server_id', 'messages.server_id');
            })
            ->join('servers', 'servers.id', '=', 'messages.server_id')
            ->join('server_user', 'server_user.server_id', '=', 'messages.server_id')
            ->leftJoin('users', 'users.id', '=', 'messages.user_id')
            ->where('server_user.user_id', $user->id)
            ->select(
                'messages.id',
                'messages.server_id',
                'messages.channel_id',
                'messages.created_at',
                'servers.name as server_name',
                'channels.name as channel_name',
                'users.id as author_id',
                'users.name as author_name',
            )
            ->orderByDesc('messages.created_at')
            ->orderByDesc('messages.id')
            ->limit($limit);

        if ($driver === 'pgsql') {
            $builder->selectRaw(
                'to_json(pgroonga_snippet_html(messages.body, ?::text[], 240)) as searchable_snippets',
                [$query->postgresTextArray()],
            );
        } else {
            $builder->addSelect('messages.body as searchable_content');
        }

        if ($channelId !== null) {
            $builder->where('messages.channel_id', $channelId);
        }

        if ($driver === 'pgsql') {
            $builder->whereRaw('messages.body &@~ ?', [$query->postgresGroongaQuery()]);
        } elseif ($driver === 'sqlite' && $this->canUseTrigramFts($query)) {
            $builder
                ->join('messages_fts', 'messages_fts.rowid', '=', 'messages.id')
                ->whereRaw('messages_fts MATCH ?', [$this->ftsQuery($query)]);
        } else {
            foreach ($query->terms as $term) {
                $builder->whereRaw(
                    "messages.body LIKE ? ESCAPE '!'",
                    ['%'.$this->escapedLikeValue($term).'%'],
                );
            }
        }

        $results = $builder->get()->map(function (\stdClass $row) use ($query, $driver): array {
            $messageId = (int) $row->id;
            $serverId = (int) $row->server_id;
            $channelId = (int) $row->channel_id;

            return [
                'type' => 'message',
                'id' => $messageId,
                'message_id' => $messageId,
                'server_id' => $serverId,
                'channel_id' => $channelId,
                'server' => [
                    'id' => $serverId,
                    'name' => $row->server_name,
                ],
                'channel' => [
                    'id' => $channelId,
                    'name' => $row->channel_name,
                ],
                'author' => $row->author_id === null ? null : [
                    'id' => (int) $row->author_id,
                    'name' => $row->author_name,
                ],
                'created_at' => $row->created_at,
                'snippet' => $driver === 'pgsql'
                    ? SearchSnippet::fromPgroonga($row->searchable_snippets ?? null)
                    : SearchSnippet::segments((string) $row->searchable_content, $query->terms),
                'url' => route('servers.channels.show', [$serverId, $channelId]).'?message='.$messageId,
            ];
        });

        return array_values($results->all());
    }

    /**
     * @return array<string, mixed>
     */
    private function fileResult(\stdClass $row): array
    {
        $fileId = (int) $row->id;
        $serverId = (int) $row->server_id;
        $channelId = $row->channel_id === null ? null : (int) $row->channel_id;
        $streamUrl = route('servers.files.stream', [$serverId, $fileId]);
        $downloadUrl = route('servers.files.download', [$serverId, $fileId]);

        return [
            'type' => 'file',
            'id' => $fileId,
            'stored_file_id' => $fileId,
            'server_id' => $serverId,
            'channel_id' => $channelId,
            'server' => [
                'id' => $serverId,
                'name' => $row->server_name,
            ],
            'channel' => $channelId === null ? null : [
                'id' => $channelId,
                'name' => $row->channel_name,
            ],
            'original_name' => $row->original_name,
            'mime_type' => $row->mime_type,
            'size' => $row->size,
            'created_at' => $row->created_at,
            'snippet' => $row->snippet_segments ?? [],
            'url' => $downloadUrl,
            'stream_url' => $streamUrl,
            'download_url' => $downloadUrl,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function meta(SearchQuery $query, int $count, bool $hasMore, int $limit): array
    {
        return [
            'query' => $query->value,
            'terms_count' => count($query->terms),
            'result_count' => $count,
            'limit' => $limit,
            'has_more' => $hasMore,
        ];
    }

    private function canUseTrigramFts(SearchQuery $query): bool
    {
        return $query->terms !== [] && collect($query->terms)->every(
            static fn (string $term): bool => mb_strlen($term) >= 3,
        );
    }

    private function escapedLikeValue(string $value): string
    {
        return strtr($value, [
            '!' => '!!',
            '%' => '!%',
            '_' => '!_',
        ]);
    }

    private function ftsQuery(SearchQuery $query): string
    {
        return collect($query->terms)
            ->map(static fn (string $term): string => '"'.str_replace('"', '', $term).'"')
            ->implode(' AND ');
    }
}
