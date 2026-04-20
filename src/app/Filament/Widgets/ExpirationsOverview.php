<?php

namespace App\Filament\Widgets;

use App\Models\Driver;
use App\Models\Inspection;
use App\Models\Registration;
use App\Models\Vehicle;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ExpirationsOverview extends StatsOverviewWidget
{
    protected ?string $heading = 'Expirations — next 30 days';

    public static function canView(): bool
    {
        $u = auth()->user();
        return $u !== null && $u->hasAnyRole(['super-admin', 'transportation-director', 'mechanic', 'viewer']);
    }

    protected function getStats(): array
    {
        $today = now()->toDateString();
        $soon  = now()->addDays(30)->toDateString();

        // Drivers
        $licenseSoon = Driver::whereBetween('license_expires_on', [$today, $soon])->count();
        $licenseExpired = Driver::where('license_expires_on', '<', $today)->count();
        $medSoon = Driver::whereBetween('dot_medical_expires_on', [$today, $soon])->count();
        $medExpired = Driver::where('dot_medical_expires_on', '<', $today)->count();

        // Vehicle inspections (most recent per vehicle)
        $inspectionsSoon = Inspection::whereBetween('expires_on', [$today, $soon])->distinct('vehicle_id')->count('vehicle_id');
        $inspectionsExpired = Inspection::where('expires_on', '<', $today)->distinct('vehicle_id')->count('vehicle_id');

        // Registrations
        $regsSoon = Registration::whereBetween('expires_on', [$today, $soon])->count();
        $regsExpired = Registration::where('expires_on', '<', $today)->count();

        $activeDrivers = Driver::where('status', Driver::STATUS_ACTIVE)->count();
        $activeVehicles = Vehicle::where('status', Vehicle::STATUS_ACTIVE)->count();

        return [
            Stat::make('Active drivers', $activeDrivers)
                ->description("{$activeVehicles} active vehicles")
                ->color('gray'),

            Stat::make('CDL licenses', $licenseSoon)
                ->description($licenseExpired > 0 ? "{$licenseExpired} already expired" : 'within 30 days')
                ->color($licenseExpired > 0 ? 'danger' : ($licenseSoon > 0 ? 'warning' : 'success')),

            Stat::make('DOT medical cards', $medSoon)
                ->description($medExpired > 0 ? "{$medExpired} already expired" : 'within 30 days')
                ->color($medExpired > 0 ? 'danger' : ($medSoon > 0 ? 'warning' : 'success')),

            Stat::make('Vehicle inspections', $inspectionsSoon)
                ->description($inspectionsExpired > 0 ? "{$inspectionsExpired} vehicles overdue" : 'within 30 days')
                ->color($inspectionsExpired > 0 ? 'danger' : ($inspectionsSoon > 0 ? 'warning' : 'success')),

            Stat::make('Registrations', $regsSoon)
                ->description($regsExpired > 0 ? "{$regsExpired} already expired" : 'within 30 days')
                ->color($regsExpired > 0 ? 'danger' : ($regsSoon > 0 ? 'warning' : 'success')),
        ];
    }

    public function getColumns(): int
    {
        return 5;
    }
}
