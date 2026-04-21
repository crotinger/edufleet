<?php

namespace App\Providers;

use App\Services\CensusGeocoder;
use App\Services\CompositeGeocoder;
use App\Services\Geocoder;
use App\Services\NominatimGeocoder;
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
        // Geocoder: try US Census (TIGER — great rural US coverage, no API key)
        // first, then fall back to Nominatim for anything Census can't resolve
        // (mostly addresses outside the US).
        $this->app->singleton(Geocoder::class, function () {
            return new CompositeGeocoder(
                new CensusGeocoder(),
                new NominatimGeocoder(),
            );
        });
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
