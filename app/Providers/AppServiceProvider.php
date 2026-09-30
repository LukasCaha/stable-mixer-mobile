<?php

namespace App\Providers;

use App\Http\NativeBrowserFallback;
use Illuminate\Support\ServiceProvider;
use Native\Mobile\Edge\Contracts\NativeRouteFallback;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(NativeRouteFallback::class, NativeBrowserFallback::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
