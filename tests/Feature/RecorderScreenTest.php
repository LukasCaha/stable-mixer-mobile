<?php

namespace Tests\Feature;

use App\Models\Recording;
use App\Models\Setting;
use App\NativeComponents\Recorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        Native::fakeBridge()
            ->respondTo('Microphone.GetStatus', ['status' => 'idle'])
            ->respondTo('Microphone.Start', []);
    }

    public function test_a_typed_code_unlocks_the_record_button(): void
    {
        Native::test(Recorder::class)
            ->assertSee('Scan QR code')
            ->set('typedCode', 'abcd123')
            ->tap('Save code')
            ->assertSee('Enter exactly 8 letters or numbers.')
            ->set('typedCode', 'ABCD1234')
            ->tap('Save code')
            ->assertSee('Record')
            ->assertSee('ABCD1234')
            ->assertDontSee('Scan QR code');

        $this->assertSame('ABCD1234', Setting::tenant());
    }

    public function test_the_demo_code_unlocks_recording(): void
    {
        Native::test(Recorder::class)
            ->tap('Use demo code')
            ->assertSee('DEMO1234')
            ->assertSee('Record')
            ->assertDontSee('Use demo code');

        $this->assertSame('DEMO1234', Setting::tenant());
    }

    public function test_record_pause_resume_stop_stores_a_pending_memo(): void
    {
        $screen = Native::test(Recorder::class)
            ->set('typedCode', 'ABCD1234')
            ->tap('Save code')
            ->tap('Record')
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
            ->assertSee('Record');

        $this->assertSame('ZXCV9876', Setting::tenant());
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
}
