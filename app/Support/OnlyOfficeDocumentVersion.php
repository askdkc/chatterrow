<?php

namespace App\Support;

use App\Models\StoredFile;

class OnlyOfficeDocumentVersion
{
    // Invalidate conversions generated before the locale-specific CJK catalog
    // and Microsoft Chinese/Korean Office aliases were introduced.
    private const NAMESPACE = 'chatterrow-onlyoffice-document-v4-regional-cjk-fonts';

    public function key(StoredFile $storedFile): string
    {
        return hash('sha256', implode("\0", [
            self::NAMESPACE,
            (string) $storedFile->getKey(),
            $storedFile->path,
            (string) $storedFile->size,
        ]));
    }
}
