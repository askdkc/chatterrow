<?php

namespace App\Support;

final class MessageMentionParser
{
    /**
     * @return list<array{kind: 'direct'|'everyone', id: string|null, raw: string, offset: int, length: int}>
     */
    public function tokens(string $body): array
    {
        $tokens = [];
        $length = strlen($body);
        $position = 0;
        $lineStart = true;
        $fenceCharacter = null;
        $fenceLength = 0;
        $inlineLength = null;

        while ($position < $length) {
            if ($fenceCharacter !== null) {
                if ($lineStart && preg_match(
                    '/\G[ \t]{0,3}('.preg_quote($fenceCharacter, '/').'{'.$fenceLength.',})[ \t]*(?:\r?\n|$)/',
                    $body,
                    $match,
                    0,
                    $position,
                ) === 1) {
                    $position += strlen($match[0]);
                    $fenceCharacter = null;
                    $fenceLength = 0;
                    $lineStart = str_ends_with($match[0], "\n");

                    continue;
                }

                $lineStart = $body[$position] === "\n";
                $position++;

                continue;
            }

            if ($inlineLength !== null) {
                if ($body[$position] === '`') {
                    $runLength = $this->runLength($body, $position, '`');

                    if ($runLength >= $inlineLength) {
                        $position += $runLength;
                        $inlineLength = null;

                        continue;
                    }
                }

                $lineStart = $body[$position] === "\n";
                $position++;

                continue;
            }

            if ($lineStart && preg_match(
                '/\G[ \t]{0,3}(`{3,}|~{3,})[^\r\n]*(?:\r?\n|$)/',
                $body,
                $match,
                0,
                $position,
            ) === 1) {
                $marker = preg_replace('/^[ \t]{0,3}/', '', $match[0]) ?? '';
                $fenceCharacter = $marker[0] ?? null;
                $fenceLength = $fenceCharacter === null ? 0 : strspn($marker, $fenceCharacter);
                $position += strlen($match[0]);
                $lineStart = str_ends_with($match[0], "\n");

                continue;
            }

            if ($body[$position] === '`') {
                $inlineLength = $this->runLength($body, $position, '`');
                $position += $inlineLength;

                continue;
            }

            if (preg_match('/\G<!everyone>/', $body, $match, 0, $position) === 1) {
                $tokens[] = [
                    'kind' => 'everyone',
                    'id' => null,
                    'raw' => $match[0],
                    'offset' => $position,
                    'length' => strlen($match[0]),
                ];
                $position += strlen($match[0]);

                continue;
            }

            if (preg_match('/\G<@([^>\r\n]*)>/', $body, $match, 0, $position) === 1) {
                $tokens[] = [
                    'kind' => 'direct',
                    'id' => $match[1],
                    'raw' => $match[0],
                    'offset' => $position,
                    'length' => strlen($match[0]),
                ];
                $position += strlen($match[0]);

                continue;
            }

            $lineStart = $body[$position] === "\n";
            $position++;
        }

        return $tokens;
    }

    /**
     * @param  callable(array{kind: 'direct'|'everyone', id: string|null, raw: string, offset: int, length: int}): string  $replacement
     */
    public function replaceTokens(string $body, callable $replacement): string
    {
        $tokens = $this->tokens($body);

        foreach (array_reverse($tokens) as $token) {
            $body = substr_replace(
                $body,
                $replacement($token),
                $token['offset'],
                $token['length'],
            );
        }

        return $body;
    }

    private function runLength(string $body, int $position, string $character): int
    {
        $length = strlen($body);
        $runLength = 0;

        while ($position + $runLength < $length && $body[$position + $runLength] === $character) {
            $runLength++;
        }

        return $runLength;
    }
}
