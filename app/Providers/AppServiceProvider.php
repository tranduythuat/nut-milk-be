<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

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
        RateLimiter::for('login', fn(Request $r) =>
        Limit::perMinute(5)->by(strtolower((string) $r->input('email')) . '|' . $r->ip()));

        RateLimiter::for('checkout', fn(Request $r) =>
        Limit::perMinute(10)->by($r->header('X-Cart-Session') ?: $r->ip()));

        RateLimiter::for('order-lookup', fn(Request $r) =>
        Limit::perMinute(10)->by($r->ip()));
    }
}
