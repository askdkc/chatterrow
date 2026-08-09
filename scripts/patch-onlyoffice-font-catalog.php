<?php

declare(strict_types=1);

/**
 * Add locale-appropriate CJK Office aliases and fallback mappings to an
 * ONLYOFFICE v2 font catalog.
 *
 * ONLYOFFICE does not use fontconfig aliases while converting OOXML files.
 * The aliases therefore have to exist in both font_selection.bin (server-side
 * conversion) and AllFonts.js (browser-side rendering).
 */
/**
 * @return array{
 *   aliases: array<string, array<int, string>>,
 *   fallbackFamilies: array<string, string>,
 *   fallbackFiles: array<string, array{regular: string, bold: string}>
 * }
 */
function onlyOfficeCjkFontProfile(
    string $locale = 'ja',
    string $fontDir = '/var/www/onlyoffice/Data/custom-fonts',
): array {
    $fontDir = rtrim($fontDir, '/');

    $profile = match ($locale) {
        'zh_CN' => [
            'sans' => 'Source Han Sans CN',
            'sansLight' => 'Source Han Sans CN Light',
            'sansFile' => 'SourceHanSansCN',
            'serif' => 'Noto Serif CJK SC',
            'serifFile' => 'NotoSerifCJKsc',
            'aliases' => [
                'sans' => ['Microsoft YaHei', 'Microsoft YaHei UI', 'SimHei', 'DengXian'],
                'light' => ['Microsoft YaHei Light', 'Microsoft YaHei UI Light', 'DengXian Light'],
                'serif' => ['SimSun', 'NSimSun', 'SimSun-ExtB', 'FangSong', 'KaiTi'],
            ],
        ],
        'zh_TW' => [
            'sans' => 'Source Han Sans TW',
            'sansLight' => 'Source Han Sans TW Light',
            'sansFile' => 'SourceHanSansTW',
            'serif' => 'Noto Serif CJK TC',
            'serifFile' => 'NotoSerifCJKtc',
            'aliases' => [
                'sans' => ['Microsoft JhengHei', 'Microsoft JhengHei UI'],
                'light' => ['Microsoft JhengHei Light', 'Microsoft JhengHei UI Light'],
                'serif' => ['MingLiU', 'PMingLiU', 'MingLiU-ExtB', 'PMingLiU-ExtB'],
            ],
        ],
        'ko' => [
            'sans' => 'Source Han Sans KR',
            'sansLight' => 'Source Han Sans KR Light',
            'sansFile' => 'SourceHanSansKR',
            'serif' => 'Noto Serif CJK KR',
            'serifFile' => 'NotoSerifCJKkr',
            'aliases' => [
                'sans' => ['Malgun Gothic', 'Gulim', 'GulimChe', 'Dotum', 'DotumChe'],
                'light' => ['Malgun Gothic Semilight'],
                'serif' => ['Batang', 'BatangChe', 'Gungsuh', 'GungsuhChe'],
            ],
        ],
        'ja' => [
            'sans' => 'Source Han Sans JP',
            'sansLight' => 'Source Han Sans JP Light',
            'sansFile' => 'SourceHanSansJP',
            'serif' => 'Noto Serif CJK JP',
            'serifFile' => 'NotoSerifCJKjp',
            'aliases' => [
                'sans' => [
                    'Yu Gothic', 'Yu Gothic UI', 'YuGothic', 'Meiryo', 'Meiryo UI',
                    'MS Gothic', 'MS PGothic', '游ゴシック', '游ゴシック体', 'メイリオ',
                    'ＭＳ ゴシック', 'ＭＳ Ｐゴシック',
                ],
                'light' => ['Yu Gothic Light', '游ゴシック Light'],
                'serif' => [
                    'Yu Mincho', 'YuMincho', 'MS Mincho', 'MS PMincho',
                    '游明朝', 'ＭＳ 明朝', 'ＭＳ Ｐ明朝',
                ],
            ],
        ],
        default => throw new InvalidArgumentException("Unsupported CJK font locale: {$locale}"),
    };

    $aliases = [
        $profile['sans'] => $profile['aliases']['sans'],
        $profile['sansLight'] => $profile['aliases']['light'],
        $profile['serif'] => $profile['aliases']['serif'],
    ];
    $fallbackFamilies = [
        'NanumGothic' => $profile['sans'],
        'Droid Sans Fallback' => $profile['serif'],
    ];
    $fallbackFiles = [
        'NanumGothic' => [
            'regular' => "{$fontDir}/{$profile['sansFile']}-Regular.otf",
            'bold' => "{$fontDir}/{$profile['sansFile']}-Bold.otf",
        ],
        'Droid Sans Fallback' => [
            'regular' => "{$fontDir}/{$profile['serifFile']}-Regular.otf",
            'bold' => "{$fontDir}/{$profile['serifFile']}-Bold.otf",
        ],
    ];

    return compact('aliases', 'fallbackFamilies', 'fallbackFiles');
}

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
function patchOnlyOfficeSelectionCatalog(string $data, ?array $profile = null): array
{
    $profile ??= onlyOfficeCjkFontProfile();
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

        foreach ($profile['aliases'][$fontName] ?? [] as $alias) {
            if (! in_array($alias, $names, true)) {
                $names[] = $alias;
                $aliasesAdded++;
            }
        }

        if (isset($profile['fallbackFiles'][$fontName])) {
            $weight = str_contains(strtolower($fontPath), 'bold') ? 'bold' : 'regular';
            $replacementPath = $profile['fallbackFiles'][$fontName][$weight];
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
function patchOnlyOfficeAllFontsJs(string $contents, ?array $profile = null): array
{
    $profile ??= onlyOfficeCjkFontProfile();
    $aliasesAdded = 0;

    foreach ($profile['aliases'] as $sourceName => $aliases) {
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

    foreach ($profile['fallbackFamilies'] as $fallbackName => $replacementName) {
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
    $options = getopt('', ['selection:', 'all-fonts:', 'all-fonts-web:', 'locale:', 'font-dir:']);
    foreach (['selection', 'all-fonts', 'all-fonts-web'] as $required) {
        if (! isset($options[$required]) || ! is_string($options[$required]) || $options[$required] === '') {
            fwrite(STDERR, "Missing --{$required}\n");

            return 2;
        }
    }

    try {
        $locale = isset($options['locale']) && is_string($options['locale'])
            ? $options['locale']
            : 'ja';
        $fontDir = isset($options['font-dir']) && is_string($options['font-dir'])
            ? $options['font-dir']
            : '/var/www/onlyoffice/Data/custom-fonts';
        $profile = onlyOfficeCjkFontProfile($locale, $fontDir);
        $selectionAliases = patchCatalogFile(
            $options['selection'],
            static fn (string $data): array => patchOnlyOfficeSelectionCatalog($data, $profile),
        );
        $serverAliases = patchCatalogFile(
            $options['all-fonts'],
            static fn (string $data): array => patchOnlyOfficeAllFontsJs($data, $profile),
        );
        $webAliases = patchCatalogFile(
            $options['all-fonts-web'],
            static fn (string $data): array => patchOnlyOfficeAllFontsJs($data, $profile),
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
