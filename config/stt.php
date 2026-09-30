<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Transcription upload
    |--------------------------------------------------------------------------
    |
    | The companion does not transcribe audio on the phone. When a recording
    | stops it is stored locally and the server receives multipart field "file"
    | with header X-Tenant. Without a base URL the memo stays on disk.
    |
    | STT_BASE_URL is the Stable Mixer site. Uploads go to
    | {base}/api/v1/memos. Set STT_UPLOAD_URL only to override that full
    | upload address, for example an emulator talking to a local server.
    |
    */

    'base_url' => env('STT_BASE_URL', 'https://stable.on-forge.com'),

    'url' => env('STT_UPLOAD_URL'),

    'timeout' => (int) env('STT_UPLOAD_TIMEOUT', 60),

    /*
    | Wi-Fi-only uploads need a connectivity signal this build does not have.
    | Retries run while the app is open. Keep this false until that exists.
    |
    */
    'wifi_only' => (bool) env('STT_WIFI_ONLY', false),

];
