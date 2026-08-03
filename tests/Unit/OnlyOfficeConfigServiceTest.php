<?php

namespace Tests\Unit;

use App\Models\StoredFile;
use App\Support\OnlyOfficeConfigService;
use Tests\TestCase;

class OnlyOfficeConfigServiceTest extends TestCase
{
    public function test_word_previews_open_fitted_to_the_page(): void
    {
        config([
            'onlyoffice.internal_url' => 'http://chatterrow.test',
            'onlyoffice.jwt_secret' => str_repeat('secret', 8),
        ]);

        $file = new StoredFile;
        $file->id = 17;
        $file->path = 'uploads/example.docx';
        $file->original_name = 'example.docx';
        $file->mime_type = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
        $file->size = 1234;

        $config = app(OnlyOfficeConfigService::class)->make($file);

        $this->assertSame(-1, $config['editorConfig']['customization']['zoom']);
    }
}
