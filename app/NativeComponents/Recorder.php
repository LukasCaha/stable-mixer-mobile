<?php

namespace App\NativeComponents;

use App\Models\Recording;
use App\Models\Setting;
use App\Services\MemoSync;
use App\Services\RecordingStore;
use App\Support\M4aDuration;
use App\Support\TenantCode;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Native\Mobile\Attributes\On;
use Native\Mobile\Attributes\Poll;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\Events\Microphone\MicrophoneCancelled;
use Native\Mobile\Events\Microphone\MicrophoneRecorded;
use Native\Mobile\Events\Scanner\CodeScanned;
use Native\Mobile\Facades\Microphone;
use Native\Mobile\Facades\Scanner;

class Recorder extends NativeComponent
{
    public ?string $tenantCode = null;

    public string $typedCode = '';

    public bool $replacingTenant = false;

    public string $phase = 'idle';

    public bool $saving = false;

    public ?string $activeId = null;

    public ?string $notice = null;

    public int $pendingCount = 0;

    public ?string $confirmingDelete = null;

    /** @var array<int, array{id: string, label: string, when: string, duration: string, size: string, status: string}> */
    public array $recent = [];

    private ?float $segmentStartedAt = null;

    private int $accumulatedSeconds = 0;

    public function mount(): void
    {
        $this->tenantCode = Setting::tenant();
        $status = Microphone::getStatus();

        if (in_array($status, ['recording', 'paused'], true)) {
            $this->phase = $status;
        }

        $this->refreshQueue();
    }

    public function render(): View
    {
        return view('native.recorder');
    }

    public function scanTenant(): void
    {
        Scanner::scan()
            ->id('tenant')
            ->prompt('Scan your 8-character tenant code')
            ->formats(['qr'])
            ->scan();
    }

    public function useDemoTenant(): void
    {
        $this->storeTenant(TenantCode::Demo);
    }

    public function saveTypedCode(): void
    {
        $code = TenantCode::fromScan($this->typedCode);

        if ($code === null) {
            $this->notice = 'Enter exactly 8 letters or numbers.';

            return;
        }

        $this->storeTenant($code);
    }

    public function beginReplaceTenant(): void
    {
        if ($this->phase !== 'idle' || $this->saving) {
            $this->notice = 'Stop the recording before changing the tenant code.';

            return;
        }

        $this->replacingTenant = true;
        $this->typedCode = '';
        $this->notice = null;
    }

    public function cancelReplaceTenant(): void
    {
        $this->replacingTenant = false;
        $this->typedCode = '';
        $this->notice = null;
    }

    public function cycleRecording(): void
    {
        if ($this->saving || $this->tenantCode === null) {
            return;
        }

        if ($this->phase === 'idle') {
            $this->startRecording();

            return;
        }

        if ($this->phase === 'recording') {
            $this->captureElapsed();
            Microphone::pause();
            $this->phase = 'paused';
            $this->notice = null;

            return;
        }

        $this->segmentStartedAt = microtime(true);
        Microphone::resume();
        $this->phase = 'recording';
        $this->notice = null;
    }

    public function stopRecording(): void
    {
        if ($this->phase === 'idle' || $this->saving) {
            return;
        }

        $this->captureElapsed();
        Microphone::stop();
        $this->phase = 'idle';
        $this->saving = true;
        $this->notice = 'Saving recording…';
    }

    public function deleteRecording(string $id): void
    {
        if ($this->confirmingDelete !== $id) {
            $this->confirmingDelete = $id;

            return;
        }

        $recording = Recording::query()->find($id);
        if ($recording !== null) {
            Storage::disk('local')->delete($recording->path);
            $recording->delete();
            Log::info('memo.deleted', ['recording_id' => $id]);
        }

        $this->confirmingDelete = null;
        $this->notice = null;
        $this->refreshQueue();
    }

    public function keepRecording(): void
    {
        $this->confirmingDelete = null;
    }

    public function retryRecording(string $id): void
    {
        $recording = Recording::query()->find($id);
        if ($recording === null || $recording->status === Recording::Synced) {
            return;
        }

        $recording->forceFill([
            'status' => Recording::Local,
            'next_attempt_at' => null,
            'last_error' => null,
        ])->save();

        app(MemoSync::class)->pushDue();
        $this->refreshQueue();
    }

    #[On(CodeScanned::class)]
    public function onCodeScanned(string $data, string $format, ?string $id = null): void
    {
        if ($id !== null && $id !== 'tenant') {
            return;
        }

        $code = TenantCode::fromScan($data);

        if ($code === null) {
            $this->notice = 'That QR code is not an 8-character tenant code.';

            return;
        }

        $this->storeTenant($code);
    }

    #[On(MicrophoneRecorded::class)]
    public function onRecorded(string $path, string $mimeType = 'audio/m4a', ?string $id = null): void
    {
        if ($this->activeId !== null && $id !== null && $id !== $this->activeId) {
            return;
        }

        try {
            app(RecordingStore::class)->keep(
                $path,
                $mimeType,
                $id ?? $this->activeId,
                $this->accumulatedSeconds > 0 ? $this->accumulatedSeconds : null,
            );
            $this->accumulatedSeconds = 0;
        } catch (\Throwable $exception) {
            $this->saving = false;
            $this->activeId = null;
            $this->phase = 'idle';
            $this->notice = 'The recording could not be saved.';
            $this->refreshQueue();

            return;
        }

        $this->saving = false;
        $this->activeId = null;
        $this->phase = 'idle';
        $this->notice = 'Saved on this phone. Waiting to sync.';
        $this->refreshQueue();
        app(MemoSync::class)->pushDue();
        $this->refreshQueue();
    }

    #[On(MicrophoneCancelled::class)]
    public function onCancelled(bool $cancelled = true, ?string $id = null): void
    {
        $this->saving = false;
        $this->activeId = null;
        $this->phase = 'idle';
        $this->notice = 'Recording was cancelled.';
    }

    #[Poll(15000)]
    public function syncPending(): void
    {
        if ($this->phase !== 'idle' || $this->saving) {
            return;
        }

        app(MemoSync::class)->pushDue();
        $this->refreshQueue();
    }

    public function recordLabel(): string
    {
        return match ($this->phase) {
            'recording' => 'Pause',
            'paused' => 'Resume',
            default => 'Record',
        };
    }

    public function scannerAvailable(): bool
    {
        return function_exists('nativephp_can') && nativephp_can('Scanner.Scan');
    }

    private function startRecording(): void
    {
        $status = Microphone::getStatus();

        if ($status === 'recording' || $status === 'paused') {
            $this->phase = $status;
            $this->notice = 'A recording is already in progress.';

            return;
        }

        $id = (string) str()->uuid();
        $this->accumulatedSeconds = 0;
        $this->segmentStartedAt = microtime(true);
        $started = Microphone::record()->id($id)->start();

        if (! $started) {
            $this->segmentStartedAt = null;
            $this->notice = 'Microphone did not start. Allow microphone access and try again.';

            return;
        }

        $this->activeId = $id;
        $this->phase = 'recording';
        $this->notice = null;
    }

    private function storeTenant(string $code): void
    {
        Setting::putTenant($code);
        $this->tenantCode = $code;
        $this->typedCode = '';
        $this->replacingTenant = false;
        $this->notice = null;
    }

    private function refreshQueue(): void
    {
        $this->pendingCount = Recording::query()
            ->where('status', '!=', Recording::Synced)
            ->count();

        $this->recent = Recording::query()
            ->latest()
            ->limit(40)
            ->get()
            ->map(function (Recording $recording) {
                if ($recording->duration === null && is_file($recording->absolutePath())) {
                    $seconds = M4aDuration::seconds($recording->absolutePath());
                    if ($seconds !== null) {
                        $recording->duration = $seconds;
                        $recording->save();
                    }
                }

                return [
                    'id' => $recording->id,
                    'label' => $recording->label(),
                    'when' => $recording->created_at?->format('M j, H:i') ?? '',
                    'duration' => $recording->durationLabel(),
                    'size' => $recording->sizeLabel(),
                    'status' => $recording->status,
                ];
            })
            ->all();
    }

    private function captureElapsed(): void
    {
        if ($this->segmentStartedAt === null) {
            return;
        }

        $this->accumulatedSeconds += (int) round(microtime(true) - $this->segmentStartedAt);
        $this->segmentStartedAt = null;
    }
}
