<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        URL::forceScheme('https');

        Gate::before(function ($user, $ability) {
            return method_exists($user, 'hasRole') && $user->hasRole('super-admin') ? true : null;
        });

        // Quicktrip rate limit: 10 requests/min per vehicle (keyed on the route
        // parameter so a shared QR can't spam multiple vehicles from one device).
        RateLimiter::for('quicktrip', function (Request $request) {
            $vehicleId = $request->route('vehicle');
            $id = is_object($vehicleId) ? $vehicleId->id : $vehicleId;
            return Limit::perMinute(10)->by('quicktrip:' . ($id ?? $request->ip()));
        });
    }
}
