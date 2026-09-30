<?php

namespace Tests\Feature;

use App\Models\Recording;
use App\Models\Setting;
use App\Services\MemoSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MemoSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_posts_the_memo_with_the_tenant_header_and_marks_it_synced(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('recordings/11111111-1111-4111-8111-111111111111.m4a', 'audio-bytes');
        Setting::putTenant('ABCD1234');
        config([
            'stt.base_url' => 'https://stable.test',
            'stt.url' => 'https://stable.test/api/v1/memos',
        ]);

        $recording = new Recording;
        $recording->id = '11111111-1111-4111-8111-111111111111';
        $recording->path = 'recordings/11111111-1111-4111-8111-111111111111.m4a';
        $recording->mime = 'audio/m4a';
        $recording->status = Recording::Local;
        $recording->save();

        Http::fake([
            'https://stable.test/api/v1/memos' => Http::response(['id' => 'srv-1', 'status' => 'queued']),
        ]);

        app(MemoSync::class)->pushDue();

        $absolute = $recording->absolutePath();

        Http::assertSent(function ($request) use ($absolute) {
            return $request->url() === 'https://stable.test/api/v1/memos'
                && $request->hasHeader('X-Tenant', 'ABCD1234')
                && str_contains($request->body(), '11111111-1111-4111-8111-111111111111.m4a')
                && ! str_contains($request->body(), $absolute);
        });

        $recording->refresh();
        $this->assertSame(Recording::Synced, $recording->status);
        $this->assertSame('srv-1', $recording->server_id);
    }

    public function test_a_dead_server_keeps_the_memo_and_schedules_a_retry(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('recordings/22222222-2222-4222-8222-222222222222.m4a', 'audio-bytes');
        Setting::putTenant('ABCD1234');
        config([
            'stt.base_url' => 'https://stable.test',
            'stt.url' => 'https://stable.test/api/v1/memos',
        ]);

        $recording = new Recording;
        $recording->id = '22222222-2222-4222-8222-222222222222';
        $recording->path = 'recordings/22222222-2222-4222-8222-222222222222.m4a';
        $recording->mime = 'audio/m4a';
        $recording->status = Recording::Local;
        $recording->save();

        Http::fake(function () {
            throw new ConnectionException('cURL error 7');
        });

        app(MemoSync::class)->pushDue();

        $recording->refresh();
        $this->assertSame(Recording::Failed, $recording->status);
        $this->assertNotNull($recording->next_attempt_at);
        $this->assertTrue($recording->next_attempt_at->isFuture());
        $this->assertStringNotContainsString($recording->absolutePath(), (string) $recording->last_error);
        $this->assertStringNotContainsString('recordings/', (string) $recording->last_error);
    }

    public function test_it_does_nothing_until_an_upload_url_is_configured(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('recordings/33333333-3333-4333-8333-333333333333.m4a', 'audio-bytes');
        Setting::putTenant('ABCD1234');
        config([
            'stt.base_url' => null,
            'stt.url' => null,
        ]);

        $recording = new Recording;
        $recording->id = '33333333-3333-4333-8333-333333333333';
        $recording->path = 'recordings/33333333-3333-4333-8333-333333333333.m4a';
        $recording->mime = 'audio/m4a';
        $recording->status = Recording::Local;
        $recording->save();

        Http::fake();

        app(MemoSync::class)->pushDue();

        Http::assertNothingSent();
        $this->assertSame(Recording::Local, Recording::query()->first()->status);
    }

    public function test_it_posts_to_the_api_path_on_the_configured_server(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('recordings/55555555-5555-4555-8555-555555555555.m4a', 'audio-bytes');
        Setting::putTenant('ABCD1234');
        config([
            'stt.base_url' => 'https://stable.test',
            'stt.url' => null,
        ]);

        $recording = new Recording;
        $recording->id = '55555555-5555-4555-8555-555555555555';
        $recording->path = 'recordings/55555555-5555-4555-8555-555555555555.m4a';
        $recording->mime = 'audio/m4a';
        $recording->status = Recording::Local;
        $recording->save();

        Http::fake([
            'https://stable.test/api/v1/memos' => Http::response(['id' => 'srv-2', 'status' => 'queued']),
        ]);

        app(MemoSync::class)->pushDue();

        Http::assertSent(fn ($request) => $request->url() === 'https://stable.test/api/v1/memos'
            && $request->hasHeader('X-Tenant', 'ABCD1234'));
        $this->assertSame('srv-2', $recording->fresh()->server_id);
    }
}
