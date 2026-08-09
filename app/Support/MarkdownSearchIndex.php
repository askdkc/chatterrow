<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

class MarkdownSearchIndex
{
    private const MESSAGE_ATTACHABLE = 'App\\Models\\Message';

    private const TODO_ATTACHABLE = 'App\\Models\\Todo';

    public function index(int $storedFileId, string $content): void
    {
        if ($this->usesSqliteFts()) {
            DB::statement('INSERT OR REPLACE INTO markdown_docs (rowid, content) VALUES (?, ?)', [
                $storedFileId,
                $content,
            ]);
        }

        DB::table('markdown_doc_contents')->upsert(
            [['stored_file_id' => $storedFileId, 'content' => $content]],
            ['stored_file_id'],
            ['content'],
        );
    }

    public function remove(int $storedFileId): void
    {
        if ($this->usesSqliteFts()) {
            DB::statement('DELETE FROM markdown_docs WHERE rowid = ?', [$storedFileId]);
        }

        DB::table('markdown_doc_contents')
            ->where('stored_file_id', $storedFileId)
            ->delete();
    }

    /**
     * Search files belonging to one server. The caller is responsible for
     * authorizing that server, as it was before the global search existed.
     *
     * @return Collection<int, stdClass>
     */
    public function search(int $serverId, string $query, int $limit = 50, ?int $channelId = null): Collection
    {
        return $this->searchRows(
            SearchQuery::from($query),
            $limit,
            null,
            $serverId,
            $channelId,
        );
    }

    /**
     * Search files across every server the user belongs to.
     *
     * @return Collection<int, stdClass>
     */
    public function searchForUser(User $user, string $query, int $limit = 50, ?int $channelId = null): Collection
    {
        return $this->searchRows(
            SearchQuery::from($query),
            $limit,
            $user->id,
            null,
            $channelId,
        );
    }

    /**
     * PGroonga is installed by provisioning/DBA work, never by an
     * application migration. Do not silently turn a PostgreSQL search into a
     * table scan when it is missing.
     */
    public function ensureAvailable(): void
    {
        if ($this->driver() !== 'pgsql') {
            return;
        }

        $extension = DB::selectOne(
            "SELECT EXISTS (SELECT 1 FROM pg_extension WHERE extname = 'pgroonga') AS available",
        );

        if (! $this->isTruthyDatabaseBoolean($extension->available ?? false)) {
            throw new SearchBackendUnavailable(
                'PGroonga is not installed. Install the database extension before enabling search.',
            );
        }
    }

    protected function usesSqliteFts(): bool
    {
        return $this->driver() === 'sqlite';
    }

    /**
     * @return Collection<int, stdClass>
     */
    private function searchRows(
        SearchQuery $query,
        int $limit,
        ?int $userId,
        ?int $serverId,
        ?int $channelId,
    ): Collection {
        if ($query->isEmpty()) {
            return collect();
        }

        $limit = min(max($limit, 1), 51);
        $driver = $this->driver();

        if ($driver === 'pgsql') {
            $this->ensureAvailable();

            return $this->searchWithPgroonga($query, $limit, $userId, $serverId, $channelId);
        }

        if ($driver === 'sqlite' && $this->usesSqliteFts() && $this->canUseTrigramFts($query)) {
            return $this->searchWithSqliteFts($query, $limit, $userId, $serverId, $channelId);
        }

        return $this->searchWithLike($query, $limit, $userId, $serverId, $channelId);
    }

    /**
     * @return Collection<int, stdClass>
     */
    private function searchWithSqliteFts(
        SearchQuery $query,
        int $limit,
        ?int $userId,
        ?int $serverId,
        ?int $channelId,
    ): Collection {
        $builder = $this->fileQuery($userId, $serverId, $channelId)
            ->join('markdown_docs', 'markdown_docs.rowid', '=', 'stored_files.id')
            ->whereRaw('markdown_docs MATCH ?', [$this->ftsQuery($query)])
            ->addSelect('markdown_docs.content as searchable_content')
            ->orderByRaw('rank')
            ->limit($limit);

        return $this->finishRows($builder->get(), $query);
    }

    /**
     * PGroonga's v2 operator receives one bound word at a time. This keeps
     * user input out of Groonga's query language and gives the same AND
     * semantics as SQLite's FTS query.
     *
     * @return Collection<int, stdClass>
     */
    private function searchWithPgroonga(
        SearchQuery $query,
        int $limit,
        ?int $userId,
        ?int $serverId,
        ?int $channelId,
    ): Collection {
        $builder = $this->fileQuery($userId, $serverId, $channelId)
            ->selectRaw(
                'to_json(pgroonga_snippet_html(markdown_doc_contents.content, ?::text[], 240)) as searchable_snippets',
                [$query->postgresTextArray()],
            )
            ->orderByDesc('stored_files.created_at')
            ->orderByDesc('stored_files.id')
            ->limit($limit);

        foreach ($query->terms as $term) {
            $builder->whereRaw('markdown_doc_contents.content &@ ?', [$term]);
        }

        return $this->finishPgroongaRows($builder->get());
    }

    /**
     * SQLite short words and non-PostgreSQL fallback drivers use literal LIKE
     * conditions. PostgreSQL never reaches this method.
     *
     * @return Collection<int, stdClass>
     */
    private function searchWithLike(
        SearchQuery $query,
        int $limit,
        ?int $userId,
        ?int $serverId,
        ?int $channelId,
    ): Collection {
        $operator = $this->driver() === 'pgsql' ? 'ilike' : 'like';
        $builder = $this->fileQuery($userId, $serverId, $channelId)
            ->addSelect('markdown_doc_contents.content as searchable_content')
            ->orderByDesc('stored_files.created_at')
            ->orderByDesc('stored_files.id')
            ->limit($limit);

        foreach ($query->terms as $term) {
            $pattern = '%'.$this->escapedLikeValue($term).'%';
            $builder->whereRaw("markdown_doc_contents.content {$operator} ? ESCAPE '!'", [$pattern]);
        }

        return $this->finishRows($builder->get(), $query);
    }

    private function fileQuery(?int $userId, ?int $serverId, ?int $channelId): Builder
    {
        $builder = DB::table('stored_files')
            ->join('markdown_doc_contents', 'markdown_doc_contents.stored_file_id', '=', 'stored_files.id')
            ->join('servers', 'servers.id', '=', 'stored_files.server_id')
            ->leftJoin('messages as attached_messages', function ($join): void {
                $join->on('attached_messages.id', '=', 'stored_files.attachable_id')
                    ->where('stored_files.attachable_type', self::MESSAGE_ATTACHABLE)
                    ->whereColumn('attached_messages.server_id', 'stored_files.server_id');
            })
            ->leftJoin('todos as attached_todos', function ($join): void {
                $join->on('attached_todos.id', '=', 'stored_files.attachable_id')
                    ->where('stored_files.attachable_type', self::TODO_ATTACHABLE);
            })
            ->leftJoin('channels as message_channels', function ($join): void {
                $join->on('message_channels.id', '=', 'attached_messages.channel_id')
                    ->whereColumn('message_channels.server_id', 'stored_files.server_id');
            })
            ->leftJoin('channels as todo_channels', function ($join): void {
                $join->on('todo_channels.id', '=', 'attached_todos.channel_id')
                    ->whereColumn('todo_channels.server_id', 'stored_files.server_id');
            })
            ->where('stored_files.markdown_status', 'ready')
            ->select(
                'stored_files.id',
                'stored_files.server_id',
                'stored_files.original_name',
                'stored_files.mime_type',
                'stored_files.size',
                'stored_files.preview_status',
                'stored_files.created_at',
                'servers.name as server_name',
                'stored_files.attachable_type',
                'stored_files.attachable_id',
            )
            ->selectRaw('COALESCE(message_channels.id, todo_channels.id) as channel_id')
            ->selectRaw('COALESCE(message_channels.name, todo_channels.name) as channel_name');

        if ($userId !== null) {
            $builder
                ->join('server_user', 'server_user.server_id', '=', 'stored_files.server_id')
                ->where('server_user.user_id', $userId);
        }

        if ($serverId !== null) {
            $builder->where('stored_files.server_id', $serverId);
        }

        if ($channelId !== null) {
            $builder->where(function ($query) use ($channelId): void {
                $query
                    ->where('message_channels.id', $channelId)
                    ->orWhere('todo_channels.id', $channelId);
            });
        }

        return $builder;
    }

    /**
     * @param  Collection<int, stdClass>  $rows
     * @return Collection<int, stdClass>
     */
    private function finishRows(Collection $rows, SearchQuery $query): Collection
    {
        return $rows->map(function (stdClass $result) use ($query): stdClass {
            $content = (string) ($result->searchable_content ?? '');
            $result->snippet = SearchSnippet::legacy($content, $query->value, $query->terms);
            $result->snippet_segments = SearchSnippet::segments($content, $query->terms);
            unset($result->searchable_content);

            return $result;
        });
    }

    /**
     * @param  Collection<int, stdClass>  $rows
     * @return Collection<int, stdClass>
     */
    private function finishPgroongaRows(Collection $rows): Collection
    {
        return $rows->map(function (stdClass $result): stdClass {
            $segments = SearchSnippet::fromPgroonga($result->searchable_snippets ?? null);
            $result->snippet_segments = $segments;
            $result->snippet = SearchSnippet::legacyFromSegments($segments);
            unset($result->searchable_snippets);

            return $result;
        });
    }

    private function canUseTrigramFts(SearchQuery $query): bool
    {
        return $query->terms !== [] && collect($query->terms)->every(
            static fn (string $term): bool => mb_strlen($term) >= 3,
        );
    }

    private function driver(): string
    {
        return DB::connection()->getDriverName();
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

    private function isTruthyDatabaseBoolean(mixed $value): bool
    {
        return in_array(strtolower((string) $value), ['1', 't', 'true', 'y', 'yes', 'on'], true);
    }
}
