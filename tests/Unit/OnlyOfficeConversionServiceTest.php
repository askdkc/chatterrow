<?php

namespace Tests\Unit;

use App\Models\StoredFile;
use App\Support\OnlyOfficeConversionService;
use App\Support\StoredFilePreviewGenerator;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class OnlyOfficeConversionServiceTest extends TestCase
{
    use RefreshDatabase;

    private const PNG_BYTES = "\x89PNG\r\n\x1a\n".'binary-data';

    private string $secret;

    protected function setUp(): void
    {
        parent::setUp();

        $this->secret = str_repeat('secret', 8);

        Storage::fake('local');
        config([
            'onlyoffice.document_server_url' => 'http://onlyoffice.test',
            'onlyoffice.public_url' => 'http://onlyoffice.test',
            'onlyoffice.internal_url' => 'http://chatterrow.test',
            'onlyoffice.jwt_secret' => $this->secret,
            'onlyoffice.enabled' => true,
        ]);
    }

    public function test_office_thumbnail_is_converted_by_onlyoffice_and_stored_as_png(): void
    {
        $file = $this->officeFile();
        Http::fake($this->converterFake());

        $path = app(StoredFilePreviewGenerator::class)->generate($file);

        $this->assertSame(
            StoredFilePreviewGenerator::officeThumbnailPath($file->id, $file->path),
            $path,
        );
        Storage::disk('local')->assertExists($path);
        $this->assertSame(self::PNG_BYTES, Storage::disk('local')->get($path));
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/converter?shardkey=')
            && isset($request->data()['token'])
        );
        Http::assertSentCount(2);
    }

    public function test_conversion_request_is_signed_with_expected_jwt_payload(): void
    {
        $file = $this->officeFile();
        Http::fake($this->converterFake());

        app(OnlyOfficeConversionService::class)->thumbnail($file);

        Http::assertSent(function (Request $request) use ($file): bool {
            if (! str_contains($request->url(), '/converter?shardkey=')) {
                return false;
            }

            $payload = json_decode(
                json_encode(JWT::decode($request->data()['token'], new Key($this->secret, 'HS256'))),
                true,
            );

            $this->assertFalse($payload['async']);
            $this->assertSame('docx', $payload['filetype']);
            $this->assertSame('png', $payload['outputtype']);
            $this->assertSame('report.docx', $payload['title']);
            $this->assertSame('/internal/onlyoffice/files/'.$file->id, parse_url($payload['url'], PHP_URL_PATH));
            $this->assertStringStartsWith('http://chatterrow.test/internal/onlyoffice/files/', $payload['url']);
            $this->assertSame(640, $payload['thumbnail']['width']);
            $this->assertSame(360, $payload['thumbnail']['height']);
            $this->assertTrue($payload['thumbnail']['first']);
            $this->assertSame(1, $payload['thumbnail']['aspect']);

            return true;
        });
    }

    public function test_result_url_from_an_unexpected_host_is_rejected(): void
    {
        $file = $this->officeFile();
        Http::fake(fn (Request $request) => str_contains($request->url(), '/converter?')
            ? Http::response([
                'endConvert' => true,
                'fileType' => 'png',
                'fileUrl' => 'http://evil.test/cache/result.png',
                'percent' => 100,
            ])
            : Http::response(self::PNG_BYTES, 200, ['Content-Type' => 'image/png'])
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('unexpected host');

        app(OnlyOfficeConversionService::class)->thumbnail($file);
    }

    public function test_relative_result_url_is_resolved_against_the_document_server(): void
    {
        $file = $this->officeFile();
        Http::fake(fn (Request $request) => str_contains($request->url(), '/converter?')
            ? Http::response([
                'endConvert' => true,
                'fileType' => 'png',
                'fileUrl' => '/cache/result.png',
                'percent' => 100,
            ])
            : Http::response(self::PNG_BYTES, 200, ['Content-Type' => 'image/png'])
        );

        $result = app(OnlyOfficeConversionService::class)->thumbnail($file);

        $this->assertSame(self::PNG_BYTES, $result);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'http://onlyoffice.test/cache/result.png');
    }

    public function test_non_zero_onlyoffice_error_is_rejected(): void
    {
        $file = $this->officeFile();
        Http::fake(fn (Request $request) => Http::response([
            'endConvert' => false,
            'error' => 5,
            'fileType' => 'png',
        ]));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('error 5');

        app(OnlyOfficeConversionService::class)->thumbnail($file);
    }

    public function test_unfinished_conversion_is_rejected(): void
    {
        $file = $this->officeFile();
        Http::fake(fn (Request $request) => Http::response([
            'endConvert' => false,
            'percent' => 50,
        ]));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('did not finish');

        app(OnlyOfficeConversionService::class)->thumbnail($file);
    }

    public function test_non_2xx_conversion_response_is_rejected(): void
    {
        $file = $this->officeFile();
        Http::fake(fn (Request $request) => Http::response('upstream error', 502));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('conversion request failed: 502');

        app(OnlyOfficeConversionService::class)->thumbnail($file);
    }

    public function test_empty_result_is_rejected(): void
    {
        $file = $this->officeFile();
        Http::fake(fn (Request $request) => str_contains($request->url(), '/converter?')
            ? Http::response([
                'endConvert' => true,
                'fileType' => 'png',
                'fileUrl' => 'http://onlyoffice.test/cache/result.png',
                'percent' => 100,
            ])
            : Http::response('', 200, ['Content-Type' => 'image/png'])
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('is empty');

        app(OnlyOfficeConversionService::class)->thumbnail($file);
    }

    public function test_result_that_is_not_a_png_is_rejected(): void
    {
        $file = $this->officeFile();
        Http::fake(fn (Request $request) => str_contains($request->url(), '/converter?')
            ? Http::response([
                'endConvert' => true,
                'fileType' => 'png',
                'fileUrl' => 'http://onlyoffice.test/cache/result.png',
                'percent' => 100,
            ])
            : Http::response('not-a-png', 200, ['Content-Type' => 'image/png'])
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('unexpected PNG result');

        app(OnlyOfficeConversionService::class)->thumbnail($file);
    }

    public function test_office_document_conversion_validates_the_zip_magic_bytes(): void
    {
        $file = $this->officeFile();
        Http::fake(fn (Request $request) => str_contains($request->url(), '/converter?')
            ? Http::response([
                'endConvert' => true,
                'fileType' => 'docx',
                'fileUrl' => 'http://onlyoffice.test/cache/result.docx',
                'percent' => 100,
            ])
            : Http::response("PK\x03\x04converted document", 200)
        );

        $result = app(OnlyOfficeConversionService::class)->document($file, 'docx');

        $this->assertStringStartsWith("PK\x03\x04", $result);
    }

    public function test_result_that_is_not_an_office_document_is_rejected(): void
    {
        $file = $this->officeFile();
        Http::fake(fn (Request $request) => str_contains($request->url(), '/converter?')
            ? Http::response([
                'endConvert' => true,
                'fileType' => 'docx',
                'fileUrl' => 'http://onlyoffice.test/cache/result.docx',
                'percent' => 100,
            ])
            : Http::response('<html>error</html>', 200, ['Content-Type' => 'text/html'])
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('unexpected Office document result');

        app(OnlyOfficeConversionService::class)->document($file, 'docx');
    }

    public function test_result_larger_than_the_size_limit_is_rejected(): void
    {
        $file = $this->officeFile();
        config(['onlyoffice.conversion_max_bytes' => 16]);
        Http::fake(fn (Request $request) => str_contains($request->url(), '/converter?')
            ? Http::response([
                'endConvert' => true,
                'fileType' => 'png',
                'fileUrl' => 'http://onlyoffice.test/cache/result.png',
                'percent' => 100,
            ])
            : Http::response(self::PNG_BYTES.'overflow', 200, ['Content-Type' => 'image/png'])
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('exceeds the size limit');

        app(OnlyOfficeConversionService::class)->thumbnail($file);
    }

    private function officeFile(): StoredFile
    {
        $file = StoredFile::withoutEvents(fn (): StoredFile => StoredFile::factory()->create([
            'path' => 'uploads/report.docx',
            'original_name' => 'report.docx',
            'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ]));
        Storage::disk('local')->put($file->path, 'docx source');

        return $file;
    }

    /** @return \Closure(Request): Response */
    private function converterFake(): callable
    {
        return fn (Request $request) => str_contains($request->url(), '/converter?')
            ? Http::response([
                'endConvert' => true,
                'fileType' => 'png',
                'fileUrl' => 'http://onlyoffice.test/cache/result.png',
                'percent' => 100,
            ])
            : Http::response(self::PNG_BYTES, 200, ['Content-Type' => 'image/png']);
    }
}
