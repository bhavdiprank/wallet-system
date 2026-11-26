<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\{RateLimiter, Schema};
use Illuminate\Http\Request;
use Illuminate\Cache\RateLimiting\Limit;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);

        RateLimiter::for('wallet-transfer', function (Request $request) {
            return Limit::perMinute(10)
                ->by($request->user()?->id ?? $request->ip());
        });
    }
}
