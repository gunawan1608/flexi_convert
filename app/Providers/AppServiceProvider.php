<?php

namespace App\Providers;

use App\Listeners\SendWelcomeEmail;
use Illuminate\Auth\Events\Verified;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

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
        // Register event listener for sending welcome email after verification
        Event::listen(
            Verified::class,
            SendWelcomeEmail::class,
        );

        // Configure custom rate limiters for password reset
        RateLimiter::for('password-reset-email', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip())
                ->response(function () {
                    return response()->json([
                        'message' => 'Terlalu banyak permintaan reset password. Silakan coba lagi dalam 1 menit.'
                    ], 429);
                });
        });

        RateLimiter::for('password-reset-update', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip())
                ->response(function () {
                    return response()->json([
                        'message' => 'Terlalu banyak percobaan reset password. Silakan coba lagi dalam 1 menit.'
                    ], 429);
                });
        });
    }
}
