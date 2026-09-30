<?php

namespace App\Support;

class TenantCode
{
    public const Demo = 'DEMO1234';

    public static function isValid(string $code): bool
    {
        return preg_match('/^[A-Za-z0-9]{8}$/', $code) === 1;
    }

    /**
     * Accept a bare code, or the same code as a QR payload in
     * ?tenant=, a tenant: prefix, or the last URL path segment.
     */
    public static function fromScan(string $payload): ?string
    {
        $payload = trim($payload);

        if (self::isValid($payload)) {
            return $payload;
        }

        if (preg_match('/(?:^|[?&])tenant=([A-Za-z0-9]{8})(?:&|$)/', $payload, $matches) === 1) {
            return $matches[1];
        }

        if (preg_match('/^tenant:([A-Za-z0-9]{8})$/i', $payload, $matches) === 1) {
            return $matches[1];
        }

        $path = parse_url($payload, PHP_URL_PATH);
        if (is_string($path) && preg_match('#/([A-Za-z0-9]{8})$#', $path, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }
}
