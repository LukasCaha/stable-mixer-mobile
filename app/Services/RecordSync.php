<?php

namespace App\Services;

use App\Models\Setting;
use App\Support\TranscriptionServer;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class RecordSync
{
    /**
     * Null when the phone could not reach the server.
     *
     * @return list<array{id: string, name: string, kind: string, knowledge: string, events: list<array{when: string, summary: string, detail: string}>}>|null
     */
    public function pull(): ?array
    {
        $url = TranscriptionServer::recordsUrl();
        $tenant = Setting::tenant();

        if ($url === null || $tenant === null) {
            return [];
        }

        try {
            $response = Http::timeout((int) config('stt.timeout', 60))
                ->withHeaders(['X-Tenant' => $tenant])
                ->acceptJson()
                ->get($url);
        } catch (ConnectionException) {
            return null;
        } catch (\Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $body = $response->json();
        if (! is_array($body)) {
            return null;
        }

        $records = [];

        foreach ($body['records'] ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }

            $name = is_string($row['name'] ?? null) ? $row['name'] : '';
            if ($name === '') {
                continue;
            }

            $events = [];
            foreach ($row['events'] ?? [] as $event) {
                if (! is_array($event)) {
                    continue;
                }
                $summary = is_string($event['summary'] ?? null) ? $event['summary'] : '';
                if ($summary === '') {
                    continue;
                }
                $when = is_string($event['occurred_on'] ?? null) ? $event['occurred_on'] : '';
                $events[] = [
                    'when' => $when,
                    'summary' => $summary,
                    'detail' => is_string($event['detail'] ?? null) ? $event['detail'] : '',
                ];
            }

            $records[] = [
                'id' => (string) ($row['id'] ?? $name),
                'name' => $name,
                'kind' => is_string($row['kind'] ?? null) ? $row['kind'] : 'animal',
                'knowledge' => is_string($row['knowledge'] ?? null) ? trim($row['knowledge']) : '',
                'events' => $events,
            ];
        }

        return $records;
    }
}
