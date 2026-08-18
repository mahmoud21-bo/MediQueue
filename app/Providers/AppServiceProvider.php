<?php

namespace App\Providers;

use App\Models\DoctorProfile;
use App\Policies\DoctorApplicationPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
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
        Gate::policy(DoctorProfile::class, DoctorApplicationPolicy::class);

        RateLimiter::for('auth-register', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));

        RateLimiter::for('auth-login', fn (Request $request) => Limit::perMinute(10)->by(
            mb_strtolower((string) $request->input('email')).'|'.$request->ip(),
        ));
    }
}
