<?php

namespace App\Support;

final class SearchSnippet
{
    /**
     * @param  list<string>  $terms
     * @return list<array{type: 'text'|'hit', text: string}>
     */
    public static function segments(string $content, array $terms, int $limit = 240): array
    {
        $plain = self::plainText($content);

        if ($plain === '') {
            return [];
        }

        $match = self::firstMatch($plain, $terms);
        $contentLength = mb_strlen($plain);

        if ($match === null) {
            $text = mb_substr($plain, 0, $limit).(
                $contentLength > $limit ? '…' : ''
            );

            return [['type' => 'text', 'text' => $text]];
        }

        $context = max(1, intdiv($limit - $match['length'], 2));
        $start = max(0, $match['position'] - $context);
        $end = min($contentLength, $match['position'] + $match['length'] + $context);
        $excerpt = mb_substr($plain, $start, $end - $start);
        $prefix = $start > 0 ? '…' : '';
        $suffix = $end < $contentLength ? '…' : '';

        return self::highlight($prefix.$excerpt.$suffix, $terms);
    }

    /**
     * Keep the old file-search response compatible while using the same safe,
     * tag-free source text as the structured global-search response.
     *
     * @param  list<string>  $terms
     */
    public static function legacy(string $content, string $query, array $terms): string
    {
        $plain = self::plainText($content);
        $query = trim($query);

        if ($plain === '') {
            return '';
        }

        $highlightTerms = $query !== '' && mb_stripos($plain, $query) !== false
            ? [$query]
            : $terms;

        return collect(self::segments($plain, $highlightTerms))
            ->map(static fn (array $segment): string => $segment['type'] === 'hit'
                ? '<mark>'.self::escapeLegacyText($segment['text']).'</mark>'
                : self::escapeLegacyText($segment['text']))
            ->implode('');
    }

    /**
     * Convert PGroonga's escaped snippet HTML into the same safe segment
     * contract used by SQLite. PGroonga escapes source text before adding its
     * keyword span, so only that exact generated span is interpreted here.
     *
     * @return list<array{type: 'text'|'hit', text: string}>
     */
    public static function fromPgroonga(mixed $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($value)) {
            return [];
        }

        $segments = [];

        foreach (array_values($value) as $index => $snippet) {
            if (! is_string($snippet) || $snippet === '') {
                continue;
            }

            if ($index > 0) {
                $segments[] = ['type' => 'text', 'text' => '…'];
            }

            $parts = preg_split(
                '/(<span class="keyword">.*?<\/span>)/s',
                $snippet,
                -1,
                PREG_SPLIT_DELIM_CAPTURE,
            ) ?: [];

            foreach ($parts as $part) {
                if ($part === '') {
                    continue;
                }

                if (preg_match('/^<span class="keyword">(.*?)<\/span>$/s', $part, $match) === 1) {
                    $segments[] = [
                        'type' => 'hit',
                        'text' => html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                    ];
                } else {
                    $segments[] = [
                        'type' => 'text',
                        'text' => html_entity_decode($part, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                    ];
                }
            }
        }

        return self::mergeAdjacent($segments);
    }

    /**
     * @param  list<array{type: 'text'|'hit', text: string}>  $segments
     */
    public static function legacyFromSegments(array $segments): string
    {
        return collect($segments)
            ->map(static fn (array $segment): string => $segment['type'] === 'hit'
                ? '<mark>'.self::escapeLegacyText($segment['text']).'</mark>'
                : self::escapeLegacyText($segment['text']))
            ->implode('');
    }

    private static function escapeLegacyText(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private static function plainText(string $content): string
    {
        $content = strip_tags($content);
        $content = preg_replace('/\s+/u', ' ', trim($content));

        return $content ?? '';
    }

    /**
     * @param  list<string>  $terms
     * @return array{position: int, length: int}|null
     */
    private static function firstMatch(string $content, array $terms): ?array
    {
        $first = null;

        foreach ($terms as $term) {
            if ($term === '') {
                continue;
            }

            $position = mb_stripos($content, $term);

            if ($position === false || ($first !== null && $position >= $first['position'])) {
                continue;
            }

            $first = [
                'position' => $position,
                'length' => mb_strlen($term),
            ];
        }

        return $first;
    }

    /**
     * @param  list<string>  $terms
     * @return list<array{type: 'text'|'hit', text: string}>
     */
    private static function highlight(string $content, array $terms): array
    {
        $terms = array_values(array_unique(array_filter($terms, static fn (string $term): bool => $term !== '')));

        if ($terms === []) {
            return [['type' => 'text', 'text' => $content]];
        }

        usort($terms, static fn (string $left, string $right): int => mb_strlen($right) <=> mb_strlen($left));
        $pattern = '/('.implode('|', array_map(static fn (string $term): string => preg_quote($term, '/'), $terms)).')/iu';

        if (preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE) !== false) {
            $segments = [];
            $offset = 0;

            foreach ($matches[1] ?? [] as [$match, $position]) {
                if ($position > $offset) {
                    $segments[] = ['type' => 'text', 'text' => substr($content, $offset, $position - $offset)];
                }

                $segments[] = ['type' => 'hit', 'text' => $match];
                $offset = $position + strlen($match);
            }

            if ($offset < strlen($content)) {
                $segments[] = ['type' => 'text', 'text' => substr($content, $offset)];
            }

            if ($segments !== []) {
                return self::mergeAdjacent($segments);
            }
        }

        return [['type' => 'text', 'text' => $content]];
    }

    /**
     * @param  list<array{type: 'text'|'hit', text: string}>  $segments
     * @return list<array{type: 'text'|'hit', text: string}>
     */
    private static function mergeAdjacent(array $segments): array
    {
        $merged = [];

        foreach ($segments as $segment) {
            $last = array_key_last($merged);

            if ($last !== null && $merged[$last]['type'] === $segment['type']) {
                $merged[$last]['text'] .= $segment['text'];
            } else {
                $merged[] = $segment;
            }
        }

        return $merged;
    }
}
