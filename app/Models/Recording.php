<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Recording extends Model
{
    use HasUuids;

    public const Local = 'local';

    public const Uploading = 'uploading';

    public const Synced = 'synced';

    public const Failed = 'failed';

    protected $fillable = [
        'path',
        'mime',
        'duration',
        'status',
        'server_id',
        'attempts',
        'next_attempt_at',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'duration' => 'integer',
            'attempts' => 'integer',
            'next_attempt_at' => 'datetime',
        ];
    }

    public function absolutePath(): string
    {
        return Storage::disk('local')->path($this->path);
    }

    public function label(): string
    {
        return match ($this->status) {
            self::Uploading => __('ui.status_uploading'),
            self::Synced => __('ui.status_synced'),
            self::Failed => __('ui.status_failed'),
            default => __('ui.status_pending'),
        };
    }

    public function durationLabel(): string
    {
        if ($this->duration === null) {
            return '—';
        }

        $seconds = max(0, $this->duration);
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $remain = $seconds % 60;

        if ($hours > 0) {
            return sprintf('%d:%02d:%02d', $hours, $minutes, $remain);
        }

        return sprintf('%d:%02d', $minutes, $remain);
    }

    public function sizeLabel(): string
    {
        if (! Storage::disk('local')->exists($this->path)) {
            return 'Missing file';
        }

        $bytes = Storage::disk('local')->size($this->path);

        if ($bytes < 1024) {
            return $bytes.' B';
        }

        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024).' KB';
        }

        return number_format($bytes / 1024 / 1024, 1).' MB';
    }
}
