<?php

namespace Tests\Unit;

use App\Models\StoredFile;
use App\Support\OnlyOfficeDocumentVersion;
use PHPUnit\Framework\TestCase;

class OnlyOfficeDocumentVersionTest extends TestCase
{
    public function test_it_uses_the_regional_cjk_font_cache_generation(): void
    {
        $file = new StoredFile;
        $file->id = 31;
        $file->path = 'uploads/example.docx';
        $file->size = 152364;

        $expected = hash('sha256', implode("\0", [
            'chatterrow-onlyoffice-document-v4-regional-cjk-fonts',
            '31',
            'uploads/example.docx',
            '152364',
        ]));

        $this->assertSame($expected, (new OnlyOfficeDocumentVersion)->key($file));
    }
}
