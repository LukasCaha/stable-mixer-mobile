<?php

namespace App\Http;

use Native\Mobile\Edge\Contracts\NativeRouteFallback;

class NativeBrowserFallback implements NativeRouteFallback
{
    public function handle(string $componentClass)
    {
        return response()->view('browser');
    }
}
