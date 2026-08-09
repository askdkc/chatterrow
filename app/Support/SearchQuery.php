<?php

namespace App\Support;

use InvalidArgumentException;

final readonly class SearchQuery
{
    public const MAX_LENGTH = 200;

    public const MAX_TERMS = 12;

    /**
     * @param  list<string>  $terms
     */
    private function __construct(
        public string $value,
        public array $terms,
    ) {}

    public static function from(string $value): self
    {
        $value = trim($value);

        if (! mb_check_encoding($value, 'UTF-8')) {
            throw new InvalidArgumentException('Search queries must be valid UTF-8.');
        }

        if (mb_strlen($value) > self::MAX_LENGTH) {
            throw new InvalidArgumentException('Search queries may not exceed 200 characters.');
        }

        $terms = $value === ''
            ? []
            : preg_split('/\s+/u', $value, -1, PREG_SPLIT_NO_EMPTY);

        if ($terms === false) {
            throw new InvalidArgumentException('Search queries must be valid UTF-8.');
        }

        if (count($terms) > self::MAX_TERMS) {
            throw new InvalidArgumentException('Search queries may contain at most 12 words.');
        }

        return new self($value, $terms);
    }

    public function isEmpty(): bool
    {
        return $this->terms === [];
    }

    public function postgresTextArray(): string
    {
        return '{'.implode(',', array_map(
            static fn (string $term): string => '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $term).'"',
            $this->terms,
        )).'}';
    }
}
