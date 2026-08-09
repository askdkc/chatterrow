<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2).'/scripts/patch-onlyoffice-font-catalog.php';

class OnlyOfficeFontCatalogPatcherTest extends TestCase
{
    public function test_it_builds_regional_office_alias_profiles(): void
    {
        $profiles = [
            'zh_CN' => ['Source Han Sans CN', 'Microsoft YaHei', 'Noto Serif CJK SC', 'SimSun', 'SourceHanSansCN'],
            'zh_TW' => ['Source Han Sans TW', 'Microsoft JhengHei', 'Noto Serif CJK TC', 'MingLiU', 'SourceHanSansTW'],
            'ko' => ['Source Han Sans KR', 'Malgun Gothic', 'Noto Serif CJK KR', 'Batang', 'SourceHanSansKR'],
        ];

        foreach ($profiles as $locale => [$sans, $sansAlias, $serif, $serifAlias, $sansFile]) {
            $profile = onlyOfficeCjkFontProfile($locale);

            $this->assertContains($sansAlias, $profile['aliases'][$sans]);
            $this->assertContains($serifAlias, $profile['aliases'][$serif]);
            $this->assertSame($sans, $profile['fallbackFamilies']['NanumGothic']);
            $this->assertStringEndsWith(
                "/{$sansFile}-Regular.otf",
                $profile['fallbackFiles']['NanumGothic']['regular'],
            );
        }
    }

    public function test_it_patches_a_simplified_chinese_catalog(): void
    {
        $profile = onlyOfficeCjkFontProfile('zh_CN');
        $catalog = $this->catalog([
            ['Source Han Sans CN', '/fonts/SourceHanSansCN-Regular.otf'],
            ['Source Han Sans CN Light', '/fonts/SourceHanSansCN-Light.otf'],
            ['Noto Serif CJK SC', '/fonts/NotoSerifCJKsc-Regular.otf'],
            ['NanumGothic', '/core/NanumGothic.ttf'],
            ['Droid Sans Fallback', '/core/DroidSansFallbackFull.ttf'],
        ], '');

        [$patched] = patchOnlyOfficeSelectionCatalog($catalog, $profile);
        $records = $this->parseCatalog($patched);

        $this->assertContains('Microsoft YaHei', $records[0]['aliases']);
        $this->assertContains('Microsoft YaHei Light', $records[1]['aliases']);
        $this->assertContains('SimSun', $records[2]['aliases']);
        $this->assertStringEndsWith('SourceHanSansCN-Regular.otf', $records[3]['path']);
        $this->assertStringEndsWith('NotoSerifCJKsc-Regular.otf', $records[4]['path']);
    }

    public function test_it_adds_office_aliases_and_remaps_converter_fallback_files(): void
    {
        $rangeTable = pack('V', 1)."NanumGothic\0".pack('VV', 0x3041, 0x3093);
        $catalog = $this->catalog([
            ['Source Han Sans JP', '/fonts/SourceHanSansJP-Regular.otf'],
            ['Source Han Sans JP Light', '/fonts/SourceHanSansJP-Light.otf'],
            ['Noto Serif CJK JP', '/fonts/NotoSerifCJKjp-Regular.otf'],
            ['NanumGothic', '/core/NanumGothic.ttf'],
            ['NanumGothic', '/core/NanumGothicBold.ttf'],
            ['Droid Sans Fallback', '/core/DroidSansFallbackFull.ttf'],
        ], $rangeTable);

        [$patched, $changes] = patchOnlyOfficeSelectionCatalog($catalog);
        $records = $this->parseCatalog($patched);

        $this->assertGreaterThan(3, $changes);
        $this->assertContains('Yu Gothic', $records[0]['aliases']);
        $this->assertContains('游ゴシック Light', $records[1]['aliases']);
        $this->assertContains('Yu Mincho', $records[2]['aliases']);
        $this->assertSame(
            '/var/www/onlyoffice/Data/custom-fonts/SourceHanSansJP-Regular.otf',
            $records[3]['path'],
        );
        $this->assertSame(
            '/var/www/onlyoffice/Data/custom-fonts/SourceHanSansJP-Bold.otf',
            $records[4]['path'],
        );
        $this->assertSame(
            '/var/www/onlyoffice/Data/custom-fonts/NotoSerifCJKjp-Regular.otf',
            $records[5]['path'],
        );
        $this->assertStringEndsWith($rangeTable, $patched);

        [$secondPass, $secondPassChanges] = patchOnlyOfficeSelectionCatalog($patched);
        $this->assertSame(0, $secondPassChanges);
        $this->assertSame($patched, $secondPass);
    }

    public function test_it_adds_browser_aliases_and_remaps_fallback_entries(): void
    {
        $catalog = <<<'JS'
window["__fonts_infos"] = [
["Droid Sans Fallback",1,0,-1,-1,-1,-1,-1,-1],
["NanumGothic",2,0,-1,-1,3,0,-1,-1],
["Noto Serif CJK JP",10,0,-1,-1,11,0,-1,-1],
["Source Han Sans JP",20,0,-1,-1,21,0,-1,-1],
["Source Han Sans JP Light",22,0,-1,-1,-1,-1,-1,-1],
];
JS;

        [$patched, $changes] = patchOnlyOfficeAllFontsJs($catalog);

        $this->assertGreaterThan(2, $changes);
        $this->assertStringContainsString('["Yu Gothic",20,0,-1,-1,21,0,-1,-1],', $patched);
        $this->assertStringContainsString('["游明朝",10,0,-1,-1,11,0,-1,-1],', $patched);
        $this->assertStringContainsString(
            '["Droid Sans Fallback",10,0,-1,-1,11,0,-1,-1],',
            $patched,
        );
        $this->assertStringContainsString('["NanumGothic",20,0,-1,-1,21,0,-1,-1],', $patched);

        [$secondPass, $secondPassChanges] = patchOnlyOfficeAllFontsJs($patched);
        $this->assertSame(0, $secondPassChanges);
        $this->assertSame($patched, $secondPass);
    }

    /**
     * @param  array<int, array{string, string}>  $records
     */
    private function catalog(array $records, string $trailingData): string
    {
        $catalog = writeUint32Le(count($records));
        foreach ($records as [$name, $path]) {
            $payload = writeCatalogString($name)
                .writeUint32Le(0)
                .writeCatalogString($path)
                .str_repeat("\0", 80);
            $catalog .= writeUint32Le(strlen($payload) + 4).$payload;
        }

        return $catalog.$trailingData;
    }

    /**
     * @return array<int, array{name: string, aliases: array<int, string>, path: string}>
     */
    private function parseCatalog(string $catalog): array
    {
        $offset = 0;
        $count = readUint32Le($catalog, $offset);
        $records = [];

        for ($index = 0; $index < $count; $index++) {
            $recordStart = $offset;
            $recordLength = readUint32Le($catalog, $offset);
            $name = readCatalogString($catalog, $offset);
            $aliasCount = readUint32Le($catalog, $offset);
            $aliases = [];
            for ($aliasIndex = 0; $aliasIndex < $aliasCount; $aliasIndex++) {
                $aliases[] = readCatalogString($catalog, $offset);
            }
            $path = readCatalogString($catalog, $offset);
            $offset = $recordStart + $recordLength;
            $records[] = compact('name', 'aliases', 'path');
        }

        return $records;
    }
}
