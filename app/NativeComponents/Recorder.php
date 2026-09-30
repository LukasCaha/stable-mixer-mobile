<?php

namespace App\NativeComponents;

use App\Models\Recording;
use App\Models\Setting;
use App\Services\AnswerSync;
use App\Services\MemoSync;
use App\Services\RecordingStore;
use App\Services\RecordSync;
use App\Services\StableLookup;
use App\Support\M4aDuration;
use App\Support\TenantCode;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Native\Mobile\Attributes\On;
use Native\Mobile\Attributes\Poll;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\Events\Microphone\MicrophoneCancelled;
use Native\Mobile\Events\Microphone\MicrophoneRecorded;
use Native\Mobile\Events\Scanner\CodeScanned;
use Native\Mobile\Events\System\AppearanceChanged;
use Native\Mobile\Facades\Microphone;
use Native\Mobile\Facades\Scanner;
use StableMixer\Speech\Speech;

class Recorder extends NativeComponent
{
    public ?string $tenantCode = null;

    public ?string $stableName = null;

    public string $typedCode = '';

    public bool $replacingTenant = false;

    public string $phase = 'idle';

    public string $tab = 'record';

    public string $uiLanguage = 'en';

    public bool $dark = false;

    public bool $serverPending = false;

    public ?string $speakingId = null;

    public bool $saving = false;

    public ?string $activeId = null;

    public ?string $notice = null;

    public int $pendingCount = 0;

    public ?string $confirmingDelete = null;

    /** @var array<int, array{id: string, label: string, when: string, duration: string, size: string, status: string}> */
    public array $recent = [];

    /** @var list<array{id: string, question: string, answer: string, when: string}> */
    public array $answers = [];

    /** @var list<array{id: string, name: string, kind: string, knowledge: string, events: list<array{when: string, summary: string, detail: string}>}> */
    public array $records = [];

    private ?float $segmentStartedAt = null;

    private int $accumulatedSeconds = 0;

    public function mount(): void
    {
        $this->uiLanguage = Setting::uiLanguage();
        App::setLocale($this->uiLanguage);
        $this->dark = isDark();
        $this->tenantCode = Setting::tenant();
        $this->stableName = Setting::stableName();
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
            ->prompt(__('ui.scan_prompt'))
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
            $this->notice = __('ui.notice_code_length');

            return;
        }

        $this->storeTenant($code);
    }

    public function beginReplaceTenant(): void
    {
        if ($this->phase !== 'idle' || $this->saving) {
            $this->notice = __('ui.notice_stop_first');

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

    public function switchUiLanguage(string $language): void
    {
        $language = $language === 'cs' ? 'cs' : 'en';
        Setting::putUiLanguage($language);
        $this->uiLanguage = $language;
        App::setLocale($language);
    }

    #[On(AppearanceChanged::class)]
    public function onAppearance(string $mode): void
    {
        $this->dark = $mode === 'dark';
    }

    public function showStable(): void
    {
        $this->tab = 'stable';
        $this->stopSpeaking();
        $this->refreshRecords();
    }

    public function showRecord(): void
    {
        $this->tab = 'record';
        $this->stopSpeaking();
    }

    public function showAsk(): void
    {
        $this->tab = 'ask';
        $this->refreshAnswers();
    }

    public function playAnswer(string $id): void
    {
        if ($this->speakingId === $id) {
            $this->stopSpeaking();

            return;
        }

        $row = collect($this->answers)->firstWhere('id', $id);
        if (! is_array($row)) {
            return;
        }

        $text = trim((string) ($row['answer'] ?? ''));
        if ($text === '') {
            return;
        }

        app(Speech::class)->speak($text);
        $this->speakingId = $id;
    }

    public function waitingForAnswer(): bool
    {
        return $this->pendingCount > 0 || $this->serverPending;
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
        $this->notice = __('ui.notice_saving');
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
            $this->notice = __('ui.notice_bad_qr');

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
            $this->notice = __('ui.notice_save_failed');
            $this->refreshQueue();

            return;
        }

        $this->saving = false;
        $this->activeId = null;
        $this->phase = 'idle';
        $this->notice = __('ui.notice_saved');
        $this->refreshQueue();
        app(MemoSync::class)->pushDue();
        $this->refreshQueue();
        $this->refreshAnswers();
    }

    #[On(MicrophoneCancelled::class)]
    public function onCancelled(bool $cancelled = true, ?string $id = null): void
    {
        $this->saving = false;
        $this->activeId = null;
        $this->phase = 'idle';
        $this->notice = __('ui.notice_cancelled');
    }

    #[Poll(15000)]
    public function syncPending(): void
    {
        if ($this->phase !== 'idle' || $this->saving) {
            return;
        }

        app(MemoSync::class)->pushDue();
        $this->refreshQueue();
        $this->refreshAnswers();
        if ($this->tab === 'stable') {
            $this->refreshRecords();
        }
    }

    public function phaseLabel(): string
    {
        return match ($this->phase) {
            'recording' => __('ui.listening'),
            'paused' => __('ui.paused'),
            default => __('ui.ready'),
        };
    }

    public function recordLabel(): string
    {
        return match ($this->phase) {
            'recording' => __('ui.pause'),
            'paused' => __('ui.resume'),
            default => $this->tab === 'ask' ? __('ui.ask') : __('ui.record'),
        };
    }

    public function headerMarkTint(): ?string
    {
        return $this->dark ? '#F6F3EC' : null;
    }

    public function dockMarkTint(): ?string
    {
        if ($this->tab === 'stable') {
            return $this->dark ? '#1C1B18' : '#F6F3EC';
        }

        return $this->dark ? '#F6F3EC' : null;
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
            $this->notice = __('ui.notice_in_progress');

            return;
        }

        $this->stopSpeaking();

        $id = (string) str()->uuid();
        $this->accumulatedSeconds = 0;
        $this->segmentStartedAt = microtime(true);
        $started = Microphone::record()->id($id)->start();

        if (! $started) {
            $this->segmentStartedAt = null;
            $this->notice = __('ui.notice_mic');

            return;
        }

        $this->activeId = $id;
        $this->phase = 'recording';
        $this->notice = null;
    }

    private function storeTenant(string $code): void
    {
        $result = app(StableLookup::class)->find($code);

        if ($result['status'] === StableLookup::Unreachable) {
            $this->notice = __('ui.notice_unreachable');

            return;
        }

        if ($result['status'] !== StableLookup::Found || $result['name'] === null) {
            $this->notice = __('ui.notice_unknown_code');

            return;
        }

        Setting::putStable($result['code'], $result['name']);
        $this->tenantCode = $result['code'];
        $this->stableName = $result['name'];
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

    private function refreshAnswers(): void
    {
        $pulled = app(AnswerSync::class)->pull();
        if ($pulled === null) {
            return;
        }

        $this->serverPending = $pulled['pending'];
        $this->answers = $pulled['answers'];

        if ($this->speakingId !== null && collect($this->answers)->doesntContain(fn (array $row): bool => $row['id'] === $this->speakingId)) {
            $this->speakingId = null;
        }
    }

    private function refreshRecords(): void
    {
        $pulled = app(RecordSync::class)->pull();
        if ($pulled === null) {
            return;
        }

        $this->records = $pulled;
    }

    private function stopSpeaking(): void
    {
        if ($this->speakingId === null) {
            return;
        }

        app(Speech::class)->stop();
        $this->speakingId = null;
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
