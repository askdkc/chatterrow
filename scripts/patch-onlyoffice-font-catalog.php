<?php

declare(strict_types=1);

/**
 * Add Japanese Office aliases and fallback mappings to an ONLYOFFICE v2
 * font catalog.
 *
 * ONLYOFFICE does not use fontconfig aliases while converting OOXML files.
 * The aliases therefore have to exist in both font_selection.bin (server-side
 * conversion) and AllFonts.js (browser-side rendering).
 */
const ONLYOFFICE_FONT_ALIASES = [
    'Source Han Sans JP' => [
        'Yu Gothic',
        'Yu Gothic UI',
        'YuGothic',
        'Meiryo',
        'Meiryo UI',
        'MS Gothic',
        'MS PGothic',
        '游ゴシック',
        '游ゴシック体',
        'メイリオ',
        'ＭＳ ゴシック',
        'ＭＳ Ｐゴシック',
    ],
    'Source Han Sans JP Light' => [
        'Yu Gothic Light',
        '游ゴシック Light',
    ],
    'Noto Serif CJK JP' => [
        'Yu Mincho',
        'YuMincho',
        'MS Mincho',
        'MS PMincho',
        '游明朝',
        'ＭＳ 明朝',
        'ＭＳ Ｐ明朝',
    ],
];

const ONLYOFFICE_FALLBACK_FONT_FAMILIES = [
    'NanumGothic' => 'Source Han Sans JP',
    'Droid Sans Fallback' => 'Noto Serif CJK JP',
];

const ONLYOFFICE_FALLBACK_FONT_FILES = [
    'NanumGothic' => [
        'regular' => '/var/www/onlyoffice/Data/custom-fonts/SourceHanSansJP-Regular.otf',
        'bold' => '/var/www/onlyoffice/Data/custom-fonts/SourceHanSansJP-Bold.otf',
    ],
    'Droid Sans Fallback' => [
        'regular' => '/var/www/onlyoffice/Data/custom-fonts/NotoSerifCJKjp-Regular.otf',
        'bold' => '/var/www/onlyoffice/Data/custom-fonts/NotoSerifCJKjp-Bold.otf',
    ],
];

function readUint32Le(string $data, int &$offset): int
{
    if ($offset + 4 > strlen($data)) {
        throw new RuntimeException('Unexpected end of ONLYOFFICE font catalog');
    }

    $value = unpack('Vvalue', substr($data, $offset, 4));
    $offset += 4;

    return (int) $value['value'];
}

function writeUint32Le(int $value): string
{
    return pack('V', $value);
}

function readCatalogString(string $data, int &$offset): string
{
    $length = readUint32Le($data, $offset);
    if ($offset + $length > strlen($data)) {
        throw new RuntimeException('Invalid string length in ONLYOFFICE font catalog');
    }

    $value = substr($data, $offset, $length);
    $offset += $length;

    return $value;
}

function writeCatalogString(string $value): string
{
    return writeUint32Le(strlen($value)).$value;
}

/** @return array{string, int} */
function patchOnlyOfficeSelectionCatalog(string $data): array
{
    $offset = 0;
    $recordCount = readUint32Le($data, $offset);
    $output = writeUint32Le($recordCount);
    $aliasesAdded = 0;

    for ($recordIndex = 0; $recordIndex < $recordCount; $recordIndex++) {
        $recordStart = $offset;
        $recordLength = readUint32Le($data, $offset);
        $recordEnd = $recordStart + $recordLength;

        if ($recordLength < 4 || $recordEnd > strlen($data)) {
            throw new RuntimeException("Invalid record {$recordIndex} in ONLYOFFICE font catalog");
        }

        $fontName = readCatalogString($data, $offset);
        $nameCount = readUint32Le($data, $offset);
        $names = [];
        for ($nameIndex = 0; $nameIndex < $nameCount; $nameIndex++) {
            $names[] = readCatalogString($data, $offset);
        }

        $fontPath = readCatalogString($data, $offset);
        if ($offset > $recordEnd) {
            throw new RuntimeException("Invalid payload in ONLYOFFICE font record {$recordIndex}");
        }
        $recordTail = substr($data, $offset, $recordEnd - $offset);
        $offset = $recordEnd;

        foreach (ONLYOFFICE_FONT_ALIASES[$fontName] ?? [] as $alias) {
            if (! in_array($alias, $names, true)) {
                $names[] = $alias;
                $aliasesAdded++;
            }
        }

        if (isset(ONLYOFFICE_FALLBACK_FONT_FILES[$fontName])) {
            $weight = str_contains(strtolower($fontPath), 'bold') ? 'bold' : 'regular';
            $replacementPath = ONLYOFFICE_FALLBACK_FONT_FILES[$fontName][$weight];
            if ($fontPath !== $replacementPath) {
                $fontPath = $replacementPath;
                $aliasesAdded++;
            }
        }

        $record = writeCatalogString($fontName).writeUint32Le(count($names));
        foreach ($names as $name) {
            $record .= writeCatalogString($name);
        }
        $record .= writeCatalogString($fontPath).$recordTail;
        $output .= writeUint32Le(strlen($record) + 4).$record;
    }

    // allfontsgen appends a Unicode-range lookup table after the font records.
    // Its entries contain font family names and character ranges, not byte
    // offsets into the record section, so they remain valid after records grow.
    $output .= substr($data, $offset);

    return [$output, $aliasesAdded];
}

/** @return array{string, int} */
function patchOnlyOfficeAllFontsJs(string $contents): array
{
    $aliasesAdded = 0;

    foreach (ONLYOFFICE_FONT_ALIASES as $sourceName => $aliases) {
        $sourcePattern = '/^\["'.preg_quote($sourceName, '/').'",([^\r\n]+)\],?$/m';
        if (preg_match($sourcePattern, $contents, $sourceMatch, PREG_OFFSET_CAPTURE) !== 1) {
            throw new RuntimeException("Missing {$sourceName} in ONLYOFFICE AllFonts.js");
        }

        $insertions = [];
        foreach ($aliases as $alias) {
            $encodedAlias = json_encode($alias, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            if (str_contains($contents, '['.$encodedAlias.',')) {
                continue;
            }

            $insertions[] = '['.$encodedAlias.','.$sourceMatch[1][0].'],';
            $aliasesAdded++;
        }

        if ($insertions !== []) {
            $sourceLine = $sourceMatch[0][0];
            $sourceOffset = $sourceMatch[0][1];
            $replacement = $sourceLine."\n".implode("\n", $insertions);
            $contents = substr_replace($contents, $replacement, $sourceOffset, strlen($sourceLine));
        }
    }

    foreach (ONLYOFFICE_FALLBACK_FONT_FAMILIES as $fallbackName => $replacementName) {
        $replacementPattern = '/^\["'.preg_quote($replacementName, '/').'",([^\r\n]+)\],?$/m';
        if (preg_match($replacementPattern, $contents, $replacementMatch) !== 1) {
            throw new RuntimeException("Missing {$replacementName} in ONLYOFFICE AllFonts.js");
        }

        $fallbackPattern = '/^(\["'.preg_quote($fallbackName, '/').'",)([^\r\n]+)(\],?)$/m';
        if (preg_match($fallbackPattern, $contents, $fallbackMatch, PREG_OFFSET_CAPTURE) !== 1) {
            throw new RuntimeException("Missing {$fallbackName} in ONLYOFFICE AllFonts.js");
        }

        if ($fallbackMatch[2][0] !== $replacementMatch[1]) {
            $start = $fallbackMatch[2][1];
            $contents = substr_replace($contents, $replacementMatch[1], $start, strlen($fallbackMatch[2][0]));
            $aliasesAdded++;
        }
    }

    return [$contents, $aliasesAdded];
}

function patchCatalogFile(string $path, callable $patcher): int
{
    $contents = file_get_contents($path);
    if ($contents === false) {
        throw new RuntimeException("Could not read {$path}");
    }

    [$patched, $aliasesAdded] = $patcher($contents);
    if (file_put_contents($path, $patched, LOCK_EX) === false) {
        throw new RuntimeException("Could not write {$path}");
    }

    return $aliasesAdded;
}

function runOnlyOfficeFontCatalogPatcher(array $arguments): int
{
    $options = getopt('', ['selection:', 'all-fonts:', 'all-fonts-web:']);
    foreach (['selection', 'all-fonts', 'all-fonts-web'] as $required) {
        if (! isset($options[$required]) || ! is_string($options[$required]) || $options[$required] === '') {
            fwrite(STDERR, "Missing --{$required}\n");

            return 2;
        }
    }

    try {
        $selectionAliases = patchCatalogFile(
            $options['selection'],
            static fn (string $data): array => patchOnlyOfficeSelectionCatalog($data),
        );
        $serverAliases = patchCatalogFile(
            $options['all-fonts'],
            static fn (string $data): array => patchOnlyOfficeAllFontsJs($data),
        );
        $webAliases = patchCatalogFile(
            $options['all-fonts-web'],
            static fn (string $data): array => patchOnlyOfficeAllFontsJs($data),
        );
    } catch (Throwable $error) {
        fwrite(STDERR, $error->getMessage()."\n");

        return 1;
    }

    printf(
        "Patched ONLYOFFICE font aliases (selection=%d, server=%d, web=%d)\n",
        $selectionAliases,
        $serverAliases,
        $webAliases,
    );

    return 0;
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    exit(runOnlyOfficeFontCatalogPatcher($argv));
}
