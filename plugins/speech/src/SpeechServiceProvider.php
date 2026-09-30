<?php

namespace StableMixer\Speech;

use Illuminate\Support\ServiceProvider;

class SpeechServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Speech::class);
    }
}
