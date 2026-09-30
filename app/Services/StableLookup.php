<?php

namespace App\Services;

use App\Support\TranscriptionServer;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class StableLookup
{
    public const Found = 'found';

    public const Unknown = 'unknown';

    public const Unreachable = 'unreachable';

    /**
     * @return array{status: string, name: ?string, code: string}
     */
    public function find(string $code): array
    {
        $code = strtoupper($code);
        $base = TranscriptionServer::baseUrl();

        if ($base === null) {
            return $this->result(self::Unreachable, null, $code);
        }

        try {
            $response = Http::timeout((int) config('stt.timeout', 60))
                ->acceptJson()
                ->get($base.'/api/v1/stables/'.$code);
        } catch (ConnectionException) {
            return $this->result(self::Unreachable, null, $code);
        }

        if ($response->serverError()) {
            return $this->result(self::Unreachable, null, $code);
        }

        $body = $response->json();
        $name = is_array($body) ? ($body['name'] ?? null) : null;
        $returned = is_array($body) ? ($body['tenant_code'] ?? null) : null;

        if (
            $response->successful()
            && is_string($name) && $name !== ''
            && is_string($returned) && strtoupper($returned) === $code
        ) {
            return $this->result(self::Found, $name, strtoupper($returned));
        }

        return $this->result(self::Unknown, null, $code);
    }

    /**
     * @return array{status: string, name: ?string, code: string}
     */
    private function result(string $status, ?string $name, string $code): array
    {
        return [
            'status' => $status,
            'name' => $name,
            'code' => $code,
        ];
    }
}
