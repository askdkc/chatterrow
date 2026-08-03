<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

class MarkdownSearchIndex
{
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
     * @return Collection<int, stdClass>
     */
    public function search(int $serverId, string $query, int $limit = 50): Collection
    {
        $query = trim($query);

        if ($query === '') {
            return collect();
        }

        if ($this->usesSqliteFts() && mb_strlen($query) >= 3) {
            return DB::table('markdown_docs')
                ->join('stored_files', 'stored_files.id', '=', 'markdown_docs.rowid')
                ->where('stored_files.server_id', $serverId)
                ->whereRaw('markdown_docs MATCH ?', [$this->ftsQuery($query)])
                ->select(
                    'stored_files.id',
                    'stored_files.original_name',
                    'stored_files.mime_type',
                    'stored_files.size',
                    'stored_files.preview_status',
                    'stored_files.created_at',
                )
                ->selectRaw("snippet(markdown_docs, 0, '<mark>', '</mark>', '…', 20) as snippet")
                ->orderByRaw('rank')
                ->limit($limit)
                ->get();
        }

        return $this->searchWithLike($serverId, $query, $limit);
    }

    protected function usesSqliteFts(): bool
    {
        return DB::connection()->getDriverName() === 'sqlite';
    }

    /**
     * Trigram FTS is SQLite-specific. Other databases and 1-2 character
     * SQLite queries use the portable content table.
     *
     * @return Collection<int, stdClass>
     */
    private function searchWithLike(int $serverId, string $query, int $limit): Collection
    {
        $operator = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
        $pattern = '%'.$this->escapedLikeValue($query).'%';

        return DB::table('markdown_doc_contents')
            ->join('stored_files', 'stored_files.id', '=', 'markdown_doc_contents.stored_file_id')
            ->where('stored_files.server_id', $serverId)
            ->whereRaw("markdown_doc_contents.content {$operator} ? ESCAPE '!'", [$pattern])
            ->select(
                'stored_files.id',
                'stored_files.original_name',
                'stored_files.mime_type',
                'stored_files.size',
                'stored_files.preview_status',
                'stored_files.created_at',
            )
            ->selectRaw('markdown_doc_contents.content as snippet')
            ->orderByDesc('stored_files.created_at')
            ->limit($limit)
            ->get()
            ->map(function (stdClass $result) use ($query): stdClass {
                $result->snippet = $this->highlightedSnippet((string) $result->snippet, $query);

                return $result;
            });
    }

    private function escapedLikeValue(string $query): string
    {
        return strtr($query, [
            '!' => '!!',
            '%' => '!%',
            '_' => '!_',
        ]);
    }

    private function highlightedSnippet(string $content, string $query): string
    {
        $position = mb_stripos($content, $query);

        if ($position === false) {
            return mb_substr($content, 0, 160).(mb_strlen($content) > 160 ? '…' : '');
        }

        $contextLength = 80;
        $queryLength = mb_strlen($query);
        $contentLength = mb_strlen($content);
        $start = max(0, $position - $contextLength);
        $end = min($contentLength, $position + $queryLength + $contextLength);
        $before = mb_substr($content, $start, $position - $start);
        $match = mb_substr($content, $position, $queryLength);
        $after = mb_substr($content, $position + $queryLength, $end - $position - $queryLength);

        return ($start > 0 ? '…' : '')
            .$before.'<mark>'.$match.'</mark>'.$after
            .($end < $contentLength ? '…' : '');
    }

    private function ftsQuery(string $query): string
    {
        $tokens = preg_split('/\s+/u', trim($query)) ?: [];
        $quoted = array_map(
            static fn (string $token): string => '"'.str_replace('"', '', $token).'"',
            $tokens,
        );

        return implode(' AND ', $quoted);
    }
}
