<?php

namespace Tests\Feature;

use App\Models\Recording;
use App\Models\Setting;
use App\NativeComponents\Recorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Native\Mobile\Events\Microphone\MicrophoneRecorded;
use Native\Mobile\Events\Scanner\CodeScanned;
use Native\Mobile\Testing\Native;
use Tests\TestCase;

class RecorderScreenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'stt.base_url' => 'https://stable.test',
            'stt.url' => null,
            'stt.wifi_only' => true,
        ]);

        Native::fakeBridge()
            ->respondTo('Microphone.GetStatus', ['status' => 'idle'])
            ->respondTo('Microphone.Start', []);
    }

    public function test_a_typed_code_unlocks_the_record_button(): void
    {
        $this->fakeStableLookup();

        Native::test(Recorder::class)
            ->assertSee('Scan QR code')
            ->set('typedCode', 'abcd123')
            ->tap('Save code')
            ->assertSee('Enter exactly 8 letters or numbers.')
            ->set('typedCode', 'ABCD1234')
            ->tap('Save code')
            ->assertSee('Record')
            ->assertSee('North Barn')
            ->assertSee('ABCD1234')
            ->assertDontSee('Scan QR code');

        $this->assertSame('ABCD1234', Setting::tenant());
        $this->assertSame('North Barn', Setting::stableName());
    }

    public function test_the_demo_code_unlocks_recording(): void
    {
        $this->fakeStableLookup();

        Native::test(Recorder::class)
            ->tap('Use demo code')
            ->assertSee('DEMO1234')
            ->assertSee('North Barn')
            ->assertSee('Record')
            ->assertDontSee('Use demo code');

        $this->assertSame('DEMO1234', Setting::tenant());
        $this->assertSame('North Barn', Setting::stableName());
    }

    public function test_record_pause_resume_stop_stores_a_pending_memo(): void
    {
        $this->fakeStableLookup();

        $screen = Native::test(Recorder::class)
            ->set('typedCode', 'ABCD1234')
            ->tap('Save code')
            ->tap('record')
            ->assertSet('phase', 'recording');

        Native::fakeBridge()->assertCalled('Microphone.Start', function (array $params) use ($screen) {
            return $params['id'] === $screen->get('activeId');
        });

        $id = $screen->get('activeId');
        $path = storage_path('framework/testing-note.m4a');
        file_put_contents($path, 'not-a-real-m4a');

        $screen
            ->tap('Pause')
            ->assertSet('phase', 'paused')
            ->tap('Resume')
            ->assertSet('phase', 'recording')
            ->tap('Stop')
            ->assertSet('saving', true)
            ->emitNative(MicrophoneRecorded::class, [
                'path' => $path,
                'mimeType' => 'audio/m4a',
                'id' => $id,
            ])
            ->assertSet('phase', 'idle')
            ->assertSet('saving', false)
            ->assertSee('1 pending sync')
            ->assertSee('Pending sync');

        Native::fakeBridge()->assertCalled('Microphone.Pause');
        Native::fakeBridge()->assertCalled('Microphone.Resume');
        Native::fakeBridge()->assertCalled('Microphone.Stop');

        $recording = Recording::query()->first();
        $this->assertNotNull($recording);
        $this->assertSame($id, $recording->id);
        $this->assertSame(Recording::Local, $recording->status);
        $this->assertSame('audio/m4a', $recording->mime);
        $this->assertFileExists($recording->absolutePath());
        $this->assertFileDoesNotExist($path);
    }

    public function test_scanning_a_valid_qr_stores_the_tenant(): void
    {
        $this->fakeStableLookup();

        Native::test(Recorder::class)
            ->tap('Scan QR code');

        Native::fakeBridge()->assertCalled('Scanner.Scan', function (array $params) {
            return $params['id'] === 'tenant' && $params['formats'] === ['qr'];
        });

        Native::test(Recorder::class)
            ->emitNative(CodeScanned::class, [
                'data' => 'https://mixer.test/t/ZXCV9876',
                'format' => 'qr',
                'id' => 'tenant',
            ])
            ->assertSee('ZXCV9876')
            ->assertSee('North Barn')
            ->assertSee('Record');

        $this->assertSame('ZXCV9876', Setting::tenant());
        $this->assertSame('North Barn', Setting::stableName());
    }

    public function test_a_saved_memo_shows_its_length_and_can_be_deleted(): void
    {
        Setting::putTenant('ABCD1234');
        Storage::disk('local')->put('recordings/44444444-4444-4444-8444-444444444444.m4a', str_repeat('a', 2048));

        $recording = new Recording;
        $recording->id = '44444444-4444-4444-8444-444444444444';
        $recording->path = 'recordings/44444444-4444-4444-8444-444444444444.m4a';
        $recording->mime = 'audio/m4a';
        $recording->duration = 84;
        $recording->status = Recording::Local;
        $recording->save();

        Native::test(Recorder::class)
            ->assertSee('1:24')
            ->assertSee('2 KB')
            ->assertSee('Pending sync')
            ->tap('Delete')
            ->assertSee('Confirm')
            ->tap('Keep')
            ->assertDontSee('Confirm');

        $this->assertNotNull(Recording::query()->find($recording->id));

        Native::test(Recorder::class)
            ->tap('Delete')
            ->tap('Confirm');

        $this->assertNull(Recording::query()->find($recording->id));
        Storage::disk('local')->assertMissing($recording->path);
    }

    public function test_an_unknown_code_does_not_replace_the_connected_stable(): void
    {
        Setting::putStable('ZXCV9876', 'Old Barn');

        Http::fake([
            'https://stable.test/api/v1/stables/ABCD1234' => Http::response(['message' => 'Stable not found.'], 404),
        ]);

        Native::test(Recorder::class)
            ->tap('Change')
            ->set('typedCode', 'ABCD1234')
            ->tap('Save code')
            ->assertSee('That code is not recognized.')
            ->assertDontSee('Record');

        $this->assertSame('ZXCV9876', Setting::tenant());
        $this->assertSame('Old Barn', Setting::stableName());
    }

    public function test_an_unreachable_server_keeps_the_previous_stable(): void
    {
        Setting::putStable('ZXCV9876', 'Old Barn');

        Http::fake(function () {
            throw new ConnectionException('down');
        });

        Native::test(Recorder::class)
            ->tap('Change')
            ->set('typedCode', 'ABCD1234')
            ->tap('Save code')
            ->assertSee('Could not reach the server.');

        $this->assertSame('ZXCV9876', Setting::tenant());
        $this->assertSame('Old Barn', Setting::stableName());
    }

    public function test_the_dock_opens_ask_and_the_ask_button_records(): void
    {
        $this->fakeStableLookup();

        $screen = Native::test(Recorder::class)
            ->assertDontSee('Ask')
            ->set('typedCode', 'ABCD1234')
            ->tap('Save code')
            ->assertSee('Ask')
            ->tap('Ask')
            ->assertSet('tab', 'ask')
            ->assertSee('Nothing answered yet.')
            ->tap('ask')
            ->assertSet('phase', 'recording');

        Native::fakeBridge()->assertCalled('Microphone.Start', function (array $params) use ($screen) {
            return $params['id'] === $screen->get('activeId');
        });
    }

    public function test_ask_lists_answers_and_plays_them(): void
    {
        Setting::putStable('ABCD1234', 'North Barn');

        Http::fake([
            'https://stable.test/api/v1/answers' => Http::response([
                'pending' => false,
                'answers' => [[
                    'id' => 'answer-1',
                    'question' => 'When was Willow shod?',
                    'answer' => 'Last Tuesday.',
                    'asked_at' => '2026-09-30T12:00:00Z',
                ]],
            ]),
        ]);

        Native::test(Recorder::class)
            ->tap('Ask')
            ->assertSee('When was Willow shod?')
            ->assertSee('Last Tuesday.')
            ->tap('Play')
            ->assertSet('speakingId', 'answer-1')
            ->assertSee('Stop')
            ->tap('Stop')
            ->assertSet('speakingId', null);

        Native::fakeBridge()->assertCalled('Speech.Speak', function (array $params) {
            return $params['text'] === 'Last Tuesday.';
        });
        Native::fakeBridge()->assertCalled('Speech.Stop');
    }

    public function test_ask_shows_waiting_while_a_memo_is_still_on_the_phone(): void
    {
        Setting::putStable('ABCD1234', 'North Barn');
        Storage::disk('local')->put('recordings/55555555-5555-4555-8555-555555555555.m4a', 'audio');

        $recording = new Recording;
        $recording->id = '55555555-5555-4555-8555-555555555555';
        $recording->path = 'recordings/55555555-5555-4555-8555-555555555555.m4a';
        $recording->mime = 'audio/m4a';
        $recording->status = Recording::Local;
        $recording->save();

        Http::fake([
            'https://stable.test/api/v1/answers' => Http::response([
                'pending' => true,
                'answers' => [],
            ]),
        ]);

        Native::test(Recorder::class)
            ->tap('Ask')
            ->assertSee('Waiting for an answer')
            ->assertDontSee('Nothing answered yet.');
    }

    public function test_the_phone_language_is_separate_from_the_stable(): void
    {
        Native::test(Recorder::class)
            ->tap('lang-cs')
            ->assertSee('Spáruj telefon')
            ->assertSet('uiLanguage', 'cs');

        $this->assertSame('cs', Setting::uiLanguage());
    }

    public function test_the_stable_tab_lists_records(): void
    {
        Setting::putStable('ABCD1234', 'North Barn');

        Http::fake([
            'https://stable.test/api/v1/records' => Http::response([
                'records' => [[
                    'id' => 7,
                    'name' => 'Willow',
                    'kind' => 'animal',
                    'knowledge' => 'Grey mare.',
                    'events' => [[
                        'occurred_on' => '2026-09-30',
                        'summary' => 'Shod',
                        'detail' => 'Front feet.',
                    ]],
                ]],
            ]),
        ]);

        Native::test(Recorder::class)
            ->assertSee('Stable')
            ->assertSee('Record')
            ->assertSee('Ask')
            ->tap('Stable')
            ->assertSet('tab', 'stable')
            ->assertSee('Willow')
            ->assertSee('Grey mare.')
            ->assertSee('Shod')
            ->assertSee('Animal');
    }

    private function fakeStableLookup(): void
    {
        Http::fake(function ($request) {
            $path = parse_url($request->url(), PHP_URL_PATH);
            $code = strtoupper(basename(is_string($path) ? $path : ''));

            return Http::response([
                'name' => 'North Barn',
                'tenant_code' => $code,
            ]);
        });
    }
}
