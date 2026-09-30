<?php

namespace App\Http;

use Native\Mobile\Edge\Contracts\NativeRouteFallback;

class NativeBrowserFallback implements NativeRouteFallback
{
    public function handle(string $componentClass)
    {
        return response(
            'Stable Mixer companion runs on the phone. Build the Android app to record.',
            200,
            ['Content-Type' => 'text/plain; charset=UTF-8'],
        );
    }
}
