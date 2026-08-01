<?php

return [
    'enabled' => (bool) env('ONLYOFFICE_ENABLED', false),
    'document_server_url' => env('ONLYOFFICE_DOCUMENT_SERVER_URL', 'http://onlyoffice-documentserver'),
    'public_url' => env('ONLYOFFICE_PUBLIC_URL', 'https://office.example.com'),
    'internal_url' => env('APP_ONLYOFFICE_INTERNAL_URL', 'http://app'),
    'jwt_secret' => env('ONLYOFFICE_JWT_SECRET', ''),
    'download_ttl' => (int) env('ONLYOFFICE_INTERNAL_DOWNLOAD_TTL', 300),
    'allow_download' => (bool) env('ONLYOFFICE_ALLOW_DOWNLOAD', false),
    'allow_print' => (bool) env('ONLYOFFICE_ALLOW_PRINT', false),
    'allow_copy' => (bool) env('ONLYOFFICE_ALLOW_COPY', true),
];
