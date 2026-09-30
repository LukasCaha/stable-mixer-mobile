<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Transcription upload
    |--------------------------------------------------------------------------
    |
    | The companion does not transcribe audio on the phone. When a recording
    | stops it is stored locally and this URL receives multipart field "file"
    | with header X-Tenant. Leave the URL empty until the server exists; the
    | memo stays on disk as pending sync.
    |
    | Point this at the computer running the API. An emulator reaches the
    | host machine at http://10.0.2.2:<port>. A phone on the same Wi-Fi
    | needs the computer's LAN address.
    |
    */

    'url' => env('STT_UPLOAD_URL'),

    'timeout' => (int) env('STT_UPLOAD_TIMEOUT', 60),

    /*
    | Wi-Fi-only uploads need a connectivity signal this build does not have.
    | Retries run while the app is open. Keep this false until that exists.
    |
    */
    'wifi_only' => (bool) env('STT_WIFI_ONLY', false),

];
