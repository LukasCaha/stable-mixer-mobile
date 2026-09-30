<?php

namespace App\Services;

use App\Models\Recording;
use App\Support\M4aDuration;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class RecordingStore
{
    public function keep(string $sourcePath, string $mime, ?string $id = null, ?int $elapsedSeconds = null): Recording
    {
        if ($sourcePath === '' || ! is_file($sourcePath)) {
            throw new \RuntimeException('Recording file is missing.');
        }

        $id = $id && Str::isUuid($id) ? $id : (string) Str::uuid();
        $relative = 'recordings/'.$id.'.m4a';

        $stream = fopen($sourcePath, 'r');
        if ($stream === false) {
            throw new \RuntimeException('Recording file is missing.');
        }

        try {
            Storage::disk('local')->writeStream($relative, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        if (realpath($sourcePath) !== realpath(Storage::disk('local')->path($relative))) {
            @unlink($sourcePath);
        }

        Log::info('memo.stored', ['recording_id' => $id]);

        $absolute = Storage::disk('local')->path($relative);
        $fromFile = M4aDuration::seconds($absolute);

        $recording = new Recording;
        $recording->id = $id;
        $recording->path = $relative;
        $recording->mime = $mime !== '' ? $mime : 'audio/m4a';
        $recording->duration = $fromFile ?? ($elapsedSeconds !== null && $elapsedSeconds > 0 ? $elapsedSeconds : null);
        $recording->status = Recording::Local;
        $recording->save();

        return $recording;
    }
}
