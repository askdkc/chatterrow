<?php

namespace App\Support;

use App\Models\StoredFile;

class OnlyOfficeDocumentVersion
{
    private const NAMESPACE = 'chatter-onlyoffice-document-v1';

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
