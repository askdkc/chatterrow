<?php

namespace App\Support;

use Firebase\JWT\JWT;
use RuntimeException;

class OnlyOfficeTokenService
{
    /** @param array<string, mixed> $payload */
    public function encode(array $payload): string
    {
        $secret = trim((string) config('onlyoffice.jwt_secret', ''));

        if ($secret === '') {
            throw new RuntimeException('ONLYOFFICE JWT secret is not configured.');
        }

        return JWT::encode($payload, $secret, 'HS256');
    }
}
