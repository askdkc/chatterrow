<?php

namespace App\Support;

use App\Models\StoredFile;

class OnlyOfficeDocumentVersion
{
    // Invalidate DocumentServer conversions generated before the Source Han /
    // Noto CJK catalog and Office fallback mappings were introduced.
    private const NAMESPACE = 'chatterrow-onlyoffice-document-v3-source-han-noto-cjk';

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
