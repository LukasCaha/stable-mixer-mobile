<?php

namespace App\Services;

use App\Models\Recording;
use App\Models\Setting;
use App\Support\TranscriptionServer;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MemoSync
{
    public function pushDue(): void
    {
        if (config('stt.wifi_only')) {
            return;
        }

        $url = TranscriptionServer::uploadUrl();
        if ($url === null) {
            return;
        }

        $tenant = Setting::tenant();
        if ($tenant === null) {
            return;
        }

        $this->releaseStaleUploads();

        $recording = Recording::query()
            ->whereIn('status', [Recording::Local, Recording::Failed])
            ->where(function ($query) {
                $query->whereNull('next_attempt_at')
                    ->orWhere('next_attempt_at', '<=', now());
            })
            ->orderBy('created_at')
            ->first();

        if ($recording === null) {
            return;
        }

        $this->upload($recording, $tenant, $url);
    }

    public function upload(Recording $recording, string $tenant, string $url): void
    {
        $absolute = $recording->absolutePath();

        if (! is_file($absolute)) {
            $recording->forceFill([
                'status' => Recording::Failed,
                'attempts' => $recording->attempts + 1,
                'next_attempt_at' => now()->addDay(),
                'last_error' => 'Recording file is missing.',
            ])->save();

            Log::warning('memo.upload_missing', ['recording_id' => $recording->id]);

            return;
        }

        $recording->forceFill([
            'status' => Recording::Uploading,
            'attempts' => $recording->attempts + 1,
            'last_error' => null,
        ])->save();

        try {
            $bytes = file_get_contents($absolute);
            if ($bytes === false) {
                throw new \RuntimeException('Recording file is missing.');
            }

            $response = Http::timeout((int) config('stt.timeout', 60))
                ->withHeaders(['X-Tenant' => $tenant])
                ->attach(
                    'file',
                    $bytes,
                    $recording->id.'.m4a',
                    ['Content-Type' => $recording->mime ?: 'audio/m4a'],
                )
                ->post($url);
        } catch (ConnectionException $exception) {
            $this->markFailed($recording, 'Could not reach the transcription server.', $absolute);

            return;
        } catch (\Throwable $exception) {
            $this->markFailed($recording, $exception->getMessage(), $absolute);

            return;
        }

        $body = $response->json();
        $serverId = is_array($body) ? ($body['id'] ?? null) : null;

        if ($response->successful() && is_string($serverId) && $serverId !== '') {
            $recording->forceFill([
                'status' => Recording::Synced,
                'server_id' => $serverId,
                'next_attempt_at' => null,
                'last_error' => null,
            ])->save();

            Log::info('memo.uploaded', [
                'recording_id' => $recording->id,
                'server_id' => $serverId,
            ]);

            return;
        }

        $this->markFailed(
            $recording,
            'Server responded '.$response->status().'.',
            $absolute,
        );
    }

    private function markFailed(Recording $recording, string $message, string $absolute): void
    {
        $message = str_replace($absolute, 'recording', $message);
        $message = mb_substr($message, 0, 160);

        $recording->forceFill([
            'status' => Recording::Failed,
            'next_attempt_at' => now()->addSeconds($this->backoffSeconds($recording->attempts)),
            'last_error' => $message,
        ])->save();

        Log::warning('memo.upload_failed', [
            'recording_id' => $recording->id,
            'attempt' => $recording->attempts,
        ]);
    }

    private function backoffSeconds(int $attempts): int
    {
        return match (true) {
            $attempts <= 1 => 5,
            $attempts === 2 => 15,
            $attempts === 3 => 60,
            $attempts === 4 => 300,
            default => 900,
        };
    }

    private function releaseStaleUploads(): void
    {
        Recording::query()
            ->where('status', Recording::Uploading)
            ->where('updated_at', '<=', now()->subMinutes(2))
            ->update([
                'status' => Recording::Failed,
                'next_attempt_at' => now(),
                'last_error' => 'Upload was interrupted.',
            ]);
    }
}
