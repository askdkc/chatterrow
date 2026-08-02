<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

class MarkdownSearchIndex
{
    public function index(int $storedFileId, string $content): void
    {
        DB::statement('INSERT OR REPLACE INTO markdown_docs (rowid, content) VALUES (?, ?)', [
            $storedFileId,
            $content,
        ]);

        DB::statement(
            'INSERT INTO markdown_doc_contents (stored_file_id, content) VALUES (?, ?)
             ON CONFLICT(stored_file_id) DO UPDATE SET content = excluded.content',
            [$storedFileId, $content],
        );
    }

    public function remove(int $storedFileId): void
    {
        DB::statement('DELETE FROM markdown_docs WHERE rowid = ?', [$storedFileId]);
        DB::statement('DELETE FROM markdown_doc_contents WHERE stored_file_id = ?', [$storedFileId]);
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

        if (mb_strlen($query) >= 3) {
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

        // Trigram FTS needs 3+ characters; fall back to LIKE for short queries.
        return DB::table('markdown_doc_contents')
            ->join('stored_files', 'stored_files.id', '=', 'markdown_doc_contents.stored_file_id')
            ->where('stored_files.server_id', $serverId)
            ->where('markdown_doc_contents.content', 'like', '%'.$query.'%')
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
            ->get();
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
