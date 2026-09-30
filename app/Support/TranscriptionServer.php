<?php

namespace App\Support;

class TranscriptionServer
{
    public static function baseUrl(): ?string
    {
        $base = config('stt.base_url');

        if (! is_string($base) || trim($base) === '') {
            return null;
        }

        return rtrim($base, '/');
    }

    public static function uploadUrl(): ?string
    {
        $override = config('stt.url');

        if (is_string($override) && $override !== '') {
            return $override;
        }

        $base = self::baseUrl();

        return $base === null ? null : $base.'/api/v1/memos';
    }

    public static function answersUrl(): ?string
    {
        $base = self::baseUrl();

        return $base === null ? null : $base.'/api/v1/answers';
    }

    public static function recordsUrl(): ?string
    {
        $base = self::baseUrl();

        return $base === null ? null : $base.'/api/v1/records';
    }
}
