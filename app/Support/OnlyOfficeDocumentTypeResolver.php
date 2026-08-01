<?php

namespace App\Support;

use App\Models\StoredFile;
use Illuminate\Support\Str;

class OnlyOfficeDocumentTypeResolver
{
    /** @var array<string, string> */
    private const DOCUMENT_TYPES = [
        'xlsx' => 'cell',
        'xls' => 'cell',
        'xlsm' => 'cell',
        'ods' => 'cell',
        'docx' => 'word',
        'doc' => 'word',
        'odt' => 'word',
        'pptx' => 'slide',
        'ppt' => 'slide',
        'odp' => 'slide',
    ];

    /**
     * Resolve a stored file into the ONLYOFFICE document metadata.
     *
     * @return array{fileType: string, documentType: string}|null
     */
    public function resolve(StoredFile $storedFile): ?array
    {
        $fileType = Str::lower(pathinfo($storedFile->original_name, PATHINFO_EXTENSION));

        if (! isset(self::DOCUMENT_TYPES[$fileType]) || $this->isObviousMimeMismatch($storedFile->mime_type)) {
            return null;
        }

        return [
            'fileType' => $fileType,
            'documentType' => self::DOCUMENT_TYPES[$fileType],
        ];
    }

    public function supports(StoredFile $storedFile): bool
    {
        return $this->resolve($storedFile) !== null;
    }

    private function isObviousMimeMismatch(?string $mimeType): bool
    {
        $mimeType = Str::lower(trim((string) $mimeType));

        return $mimeType !== '' && (
            Str::startsWith($mimeType, 'image/')
            || Str::startsWith($mimeType, 'video/')
            || Str::startsWith($mimeType, 'audio/')
            || Str::startsWith($mimeType, 'text/')
        );
    }
}
