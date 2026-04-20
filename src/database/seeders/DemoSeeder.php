<?php

namespace Database\Seeders;

use App\Models\Driver;
use App\Models\Inspection;
use App\Models\MaintenanceRecord;
use App\Models\Registration;
use App\Models\Route;
use App\Models\Trip;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Populates the database with realistic demo data for a small K-12 fleet
 * (≈4 buses, a few light vehicles, a handful of drivers, 3 routes × AM/PM,
 * ~3 weeks of trips). Idempotent — can be re-run safely.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $vehicles = $this->vehicles();
            $drivers  = $this->drivers();
            $routes   = $this->routes($vehicles, $drivers);

            $this->registrations($vehicles);
            $this->inspections($vehicles);
            $this->maintenance($vehicles);
            $this->trips($vehicles, $drivers, $routes);
        });
    }

    /** @return array<string, Vehicle> keyed by unit_number */
    private function vehicles(): array
    {
        $defs = [
            ['type' => 'bus', 'unit_number' => '11', 'make' => 'Blue Bird', 'model' => 'Vision', 'year' => 2016, 'vin' => '1BAKGCPA0GF115511', 'license_plate' => 'USD444-11', 'fuel_type' => 'diesel', 'odometer_miles' => 118500, 'capacity_passengers' => 72, 'status' => 'active', 'acquired_on' => '2016-08-01'],
            ['type' => 'bus', 'unit_number' => '12', 'make' => 'Blue Bird', 'model' => 'Vision', 'year' => 2021, 'vin' => '1BAKGCPA0MF123456', 'license_plate' => 'USD444-12', 'fuel_type' => 'diesel', 'odometer_miles' => 42118, 'capacity_passengers' => 72, 'status' => 'active'],
            ['type' => 'bus', 'unit_number' => '14', 'make' => 'Thomas', 'model' => 'Saf-T-Liner C2', 'year' => 2023, 'vin' => '4UZA4FF83PCCP1414', 'license_plate' => 'USD444-14', 'fuel_type' => 'diesel', 'odometer_miles' => 18750, 'capacity_passengers' => 77, 'status' => 'active', 'acquired_on' => '2023-07-15'],
            ['type' => 'bus', 'unit_number' => '18', 'make' => 'Blue Bird', 'model' => 'Vision Propane', 'year' => 2022, 'vin' => '1BAKGCPA0NF118818', 'license_plate' => 'USD444-18', 'fuel_type' => 'propane', 'odometer_miles' => 31200, 'capacity_passengers' => 72, 'status' => 'active', 'acquired_on' => '2022-06-20', 'notes' => 'Propane conversion; requires annual tank inspection.'],
            ['type' => 'light_vehicle', 'unit_number' => '3', 'make' => 'Ford', 'model' => 'F-150', 'year' => 2019, 'license_plate' => 'USD444-03', 'fuel_type' => 'gasoline', 'odometer_miles' => 88055, 'status' => 'active'],
            ['type' => 'light_vehicle', 'unit_number' => '7', 'make' => 'Ford', 'model' => 'Transit 350 Van', 'year' => 2020, 'vin' => '1FTBW2CM0LKA55007', 'license_plate' => 'USD444-07', 'fuel_type' => 'gasoline', 'odometer_miles' => 64230, 'capacity_passengers' => 12, 'status' => 'active', 'acquired_on' => '2020-09-10'],
            ['type' => 'light_vehicle', 'unit_number' => '9', 'make' => 'Chevrolet', 'model' => 'Suburban', 'year' => 2018, 'license_plate' => 'USD444-09', 'fuel_type' => 'gasoline', 'odometer_miles' => 112400, 'status' => 'active', 'notes' => 'Activities director / small-group transport.'],
        ];

        $out = [];
        foreach ($defs as $d) {
            $out[$d['unit_number'] . '-' . $d['type']] = Vehicle::updateOrCreate(
                ['type' => $d['type'], 'unit_number' => $d['unit_number']],
                $d
            );
        }
        return $out;
    }

    /** @return array<string, Driver> keyed by last_name */
    private function drivers(): array
    {
        $defs = [
            // existing
            ['first_name' => 'Dana',    'last_name' => 'Miller',    'employee_id' => 'E1001', 'email' => 'dmiller@usd444.org',   'phone' => '620-555-0142', 'hired_on' => '2018-08-15', 'status' => 'active',   'license_state' => 'KS', 'license_number' => 'K01-11-2345', 'license_class' => 'B', 'license_issued_on' => '2024-02-10', 'license_expires_on' => now()->addMonths(20)->toDateString(), 'endorsements' => ['P','S'], 'dot_medical_expires_on' => now()->addMonths(10)->toDateString(), 'first_aid_cpr_expires_on' => now()->addMonths(6)->toDateString()],
            ['first_name' => 'Rafael',  'last_name' => 'Ortiz',     'employee_id' => 'E1002', 'email' => 'rortiz@usd444.org',    'hired_on' => '2021-01-04', 'status' => 'active',   'license_state' => 'KS', 'license_number' => 'K02-33-7788', 'license_class' => 'B', 'license_expires_on' => now()->addDays(18)->toDateString(),  'endorsements' => ['P','S'], 'dot_medical_expires_on' => now()->addDays(42)->toDateString()],
            ['first_name' => 'Brenda',  'last_name' => 'Whitfield', 'employee_id' => 'E1003', 'hired_on' => '2015-08-20', 'status' => 'on_leave', 'license_state' => 'KS', 'license_number' => 'K03-55-9900', 'license_class' => 'B', 'license_expires_on' => now()->addYears(1)->toDateString(), 'endorsements' => ['P','S','H'], 'dot_medical_expires_on' => now()->subDays(10)->toDateString()],

            // new
            ['first_name' => 'Curtis',    'last_name' => 'Jennings', 'employee_id' => 'E1004', 'email' => 'cjennings@usd444.org', 'phone' => '620-555-0199', 'hired_on' => '2012-08-10', 'status' => 'active', 'license_state' => 'KS', 'license_number' => 'K04-12-3311', 'license_class' => 'A', 'license_issued_on' => '2023-11-05', 'license_expires_on' => now()->addYears(2)->toDateString(), 'endorsements' => ['P','S','H','N'], 'dot_medical_expires_on' => now()->addMonths(18)->toDateString(), 'first_aid_cpr_expires_on' => now()->addMonths(14)->toDateString(), 'defensive_driving_expires_on' => now()->addMonths(20)->toDateString(), 'notes' => 'Senior driver; trains new hires.'],
            ['first_name' => 'Marjorie',  'last_name' => 'Thompson', 'employee_id' => 'E1005', 'email' => 'mthompson@usd444.org', 'hired_on' => '2019-09-03', 'status' => 'active', 'license_state' => 'KS', 'license_number' => 'K05-44-5566', 'license_class' => 'B', 'license_expires_on' => now()->addDays(45)->toDateString(), 'endorsements' => ['P','S'], 'dot_medical_expires_on' => now()->addMonths(11)->toDateString(), 'first_aid_cpr_expires_on' => now()->addMonths(4)->toDateString()],
            ['first_name' => 'Dwayne',    'last_name' => 'Holloway', 'employee_id' => 'E1006', 'email' => 'dholloway@usd444.org', 'hired_on' => '2020-08-24', 'status' => 'active', 'license_state' => 'KS', 'license_number' => 'K06-77-8822', 'license_class' => 'B', 'license_expires_on' => now()->addYears(1)->addMonths(6)->toDateString(), 'endorsements' => ['P','S'], 'dot_medical_expires_on' => now()->addMonths(7)->toDateString()],
            ['first_name' => 'Lucia',     'last_name' => 'Vargas',   'employee_id' => 'E1007', 'email' => 'lvargas@usd444.org',   'hired_on' => now()->subMonths(2)->toDateString(), 'status' => 'active', 'license_state' => 'KS', 'license_number' => 'K07-99-1100', 'license_class' => 'C', 'license_expires_on' => now()->addYears(3)->toDateString(), 'endorsements' => [], 'dot_medical_expires_on' => now()->addYears(1)->toDateString(), 'notes' => 'In CDL-B endorsement training; currently sub driver only.'],
            ['first_name' => 'Pete',      'last_name' => 'Simmons',  'employee_id' => 'E1008', 'phone' => '620-555-0117', 'hired_on' => '2008-08-18', 'status' => 'active', 'license_state' => 'KS', 'license_number' => 'K08-22-3344', 'license_class' => 'B', 'license_expires_on' => now()->addMonths(22)->toDateString(), 'endorsements' => ['P','S'], 'dot_medical_expires_on' => now()->addMonths(9)->toDateString(), 'notes' => 'Semi-retired; substitute driver, activity/athletic trips only.'],
        ];

        $out = [];
        foreach ($defs as $d) {
            $out[$d['last_name']] = Driver::updateOrCreate(
                ['license_state' => $d['license_state'], 'license_number' => $d['license_number']],
                $d
            );
        }
        return $out;
    }

    /**
     * @param array<string, Vehicle> $vehicles
     * @param array<string, Driver>  $drivers
     * @return array<string, Route>
     */
    private function routes(array $vehicles, array $drivers): array
    {
        $defs = [
            ['code' => '1-AM', 'name' => 'Route 1 Morning',   'description' => 'In-town elementary pickup', 'default_vehicle' => '14-bus', 'default_driver' => 'Jennings', 'departure_time' => '07:00', 'return_time' => '08:15', 'estimated_miles' => 22, 'starting_location' => 'USD444 bus barn'],
            ['code' => '1-PM', 'name' => 'Route 1 Afternoon', 'description' => 'In-town elementary dropoff', 'default_vehicle' => '14-bus', 'default_driver' => 'Jennings', 'departure_time' => '15:15', 'return_time' => '16:20', 'estimated_miles' => 24, 'starting_location' => 'USD444 bus barn'],
            ['code' => '3-AM', 'name' => 'Route 3 Morning',   'description' => 'East county K-12 pickup',   'default_vehicle' => '18-bus', 'default_driver' => 'Holloway', 'departure_time' => '06:30', 'return_time' => '08:30', 'estimated_miles' => 55, 'starting_location' => 'USD444 bus barn'],
            ['code' => '3-PM', 'name' => 'Route 3 Afternoon', 'description' => 'East county K-12 dropoff',  'default_vehicle' => '18-bus', 'default_driver' => 'Holloway', 'departure_time' => '15:15', 'return_time' => '17:15', 'estimated_miles' => 58, 'starting_location' => 'USD444 bus barn'],
            ['code' => '5-AM', 'name' => 'Route 5 Morning',   'description' => 'K-12 pickup, north county loop', 'default_vehicle' => '12-bus', 'default_driver' => 'Miller', 'departure_time' => '06:45', 'return_time' => '08:30', 'estimated_miles' => 48, 'starting_location' => 'USD444 bus barn'],
            ['code' => '5-PM', 'name' => 'Route 5 Afternoon', 'description' => 'K-12 dropoff, north county loop', 'default_vehicle' => '12-bus', 'default_driver' => 'Miller', 'departure_time' => '15:15', 'return_time' => '17:00', 'estimated_miles' => 50, 'starting_location' => 'USD444 bus barn'],
        ];

        $out = [];
        foreach ($defs as $d) {
            $out[$d['code']] = Route::updateOrCreate(
                ['code' => $d['code']],
                [
                    'name' => $d['name'],
                    'description' => $d['description'],
                    'default_vehicle_id' => $vehicles[$d['default_vehicle']]->id,
                    'default_driver_id'  => $drivers[$d['default_driver']]->id,
                    'days_of_week' => ['mon','tue','wed','thu','fri'],
                    'departure_time' => $d['departure_time'],
                    'return_time'    => $d['return_time'],
                    'starting_location' => $d['starting_location'],
                    'estimated_miles' => $d['estimated_miles'],
                    'status' => 'active',
                ]
            );
        }
        return $out;
    }

    /** @param array<string, Vehicle> $vehicles */
    private function registrations(array $vehicles): void
    {
        $specs = [
            '11-bus'           => ['registered_on' => now()->subMonths(10)->toDateString(), 'expires_on' => now()->addMonths(2)->toDateString(),  'plate_number' => 'USD444-11', 'fee_cents' => 3500],
            '12-bus'           => ['registered_on' => now()->subMonths(11)->toDateString(), 'expires_on' => now()->addMonths(1)->toDateString(),  'plate_number' => 'USD444-12', 'fee_cents' => 3500],
            '14-bus'           => ['registered_on' => now()->subMonths(8)->toDateString(),  'expires_on' => now()->addMonths(4)->toDateString(),  'plate_number' => 'USD444-14', 'fee_cents' => 3500],
            '18-bus'           => ['registered_on' => now()->subMonths(7)->toDateString(),  'expires_on' => now()->addMonths(5)->toDateString(),  'plate_number' => 'USD444-18', 'fee_cents' => 3500],
            '3-light_vehicle'  => ['registered_on' => now()->subMonths(14)->toDateString(), 'expires_on' => now()->subDays(15)->toDateString(),   'plate_number' => 'USD444-03', 'fee_cents' => 4500],
            '7-light_vehicle'  => ['registered_on' => now()->subMonths(6)->toDateString(),  'expires_on' => now()->addMonths(6)->toDateString(),  'plate_number' => 'USD444-07', 'fee_cents' => 4500],
            '9-light_vehicle'  => ['registered_on' => now()->subMonths(9)->toDateString(),  'expires_on' => now()->addDays(25)->toDateString(),   'plate_number' => 'USD444-09', 'fee_cents' => 4500],
        ];

        foreach ($specs as $key => $spec) {
            $v = $vehicles[$key];
            Registration::updateOrCreate(
                ['state' => 'KS', 'registration_number' => "USD444-{$v->unit_number}-" . now()->year],
                array_merge($spec, ['vehicle_id' => $v->id, 'state' => 'KS'])
            );
        }
    }

    /** @param array<string, Vehicle> $vehicles */
    private function inspections(array $vehicles): void
    {
        // KHP annual inspection for each bus
        $khpSpecs = [
            '11-bus' => ['inspected_on' => now()->subMonths(8)->toDateString(),  'expires_on' => now()->addMonths(4)->toDateString(),  'result' => 'passed_with_defects', 'inspector_name' => 'Trp. J. Walker',  'certificate_number' => 'KHP-2025-00801', 'odometer_miles' => 117500],
            '12-bus' => ['inspected_on' => now()->subMonths(11)->toDateString(), 'expires_on' => now()->addDays(25)->toDateString(),   'result' => 'passed',              'inspector_name' => 'Trp. K. Reese',    'certificate_number' => 'KHP-2025-00942', 'odometer_miles' => 41500],
            '14-bus' => ['inspected_on' => now()->subMonths(5)->toDateString(),  'expires_on' => now()->addMonths(7)->toDateString(),  'result' => 'passed',              'inspector_name' => 'Trp. K. Reese',    'certificate_number' => 'KHP-2025-01066', 'odometer_miles' => 16100],
            '18-bus' => ['inspected_on' => now()->subMonths(10)->toDateString(), 'expires_on' => now()->addMonths(2)->toDateString(),  'result' => 'passed',              'inspector_name' => 'Trp. J. Walker',    'certificate_number' => 'KHP-2025-00855', 'odometer_miles' => 28800],
        ];
        foreach ($khpSpecs as $key => $spec) {
            Inspection::updateOrCreate(
                ['vehicle_id' => $vehicles[$key]->id, 'type' => 'khp_annual', 'inspected_on' => $spec['inspected_on']],
                array_merge($spec, ['vehicle_id' => $vehicles[$key]->id, 'type' => 'khp_annual'])
            );
        }

        // Internal safety inspections on light vehicles
        $internalSpecs = [
            '3-light_vehicle' => ['inspected_on' => now()->subMonths(13)->toDateString(), 'expires_on' => now()->subDays(20)->toDateString(), 'result' => 'passed_with_defects', 'inspector_name' => 'Dan Hoch (shop)', 'odometer_miles' => 86000],
            '7-light_vehicle' => ['inspected_on' => now()->subMonths(4)->toDateString(),  'expires_on' => now()->addMonths(8)->toDateString(), 'result' => 'passed', 'inspector_name' => 'Dan Hoch (shop)', 'odometer_miles' => 62000],
            '9-light_vehicle' => ['inspected_on' => now()->subMonths(6)->toDateString(),  'expires_on' => now()->addMonths(6)->toDateString(), 'result' => 'passed_with_defects', 'inspector_name' => 'Dan Hoch (shop)', 'odometer_miles' => 110100, 'notes' => 'Rear tires borderline; recheck at next service.'],
        ];
        foreach ($internalSpecs as $key => $spec) {
            Inspection::updateOrCreate(
                ['vehicle_id' => $vehicles[$key]->id, 'type' => 'internal_safety', 'inspected_on' => $spec['inspected_on']],
                array_merge($spec, ['vehicle_id' => $vehicles[$key]->id, 'type' => 'internal_safety'])
            );
        }
    }

    /** @param array<string, Vehicle> $vehicles */
    private function maintenance(array $vehicles): void
    {
        $records = [
            // Bus 11 (high miles)
            ['v' => '11-bus', 'service_type' => 'oil_change',    'performed_on' => now()->subMonths(2)->toDateString(), 'performed_by' => 'USD444 shop', 'odometer_at_service' => 117000, 'cost_cents' => 8500, 'interval_miles' => 5000, 'interval_months' => 6, 'next_due_miles' => 122000, 'next_due_on' => now()->addMonths(4)->toDateString()],
            ['v' => '11-bus', 'service_type' => 'brake_inspection','performed_on' => now()->subMonths(3)->toDateString(), 'performed_by' => 'USD444 shop', 'odometer_at_service' => 116200, 'interval_months' => 6, 'next_due_on' => now()->addMonths(3)->toDateString()],

            // Bus 12
            ['v' => '12-bus', 'service_type' => 'oil_change',      'performed_on' => now()->subMonths(4)->toDateString(),        'performed_by' => 'USD444 shop',   'odometer_at_service' => 41000, 'cost_cents' => 7500, 'interval_miles' => 5000, 'interval_months' => 6, 'next_due_miles' => 46000, 'next_due_on' => now()->subMonths(4)->addMonths(6)->toDateString()],
            ['v' => '12-bus', 'service_type' => 'brake_inspection','performed_on' => now()->subMonths(5)->subDays(15)->toDateString(), 'performed_by' => 'USD444 shop',   'odometer_at_service' => 40500, 'interval_months' => 6, 'next_due_on' => now()->addDays(15)->toDateString()],
            ['v' => '12-bus', 'service_type' => 'propane_tank_inspection', 'performed_on' => now()->subMonths(13)->toDateString(), 'performed_by' => 'Certified inspector', 'cost_cents' => 15000, 'interval_months' => 12, 'next_due_on' => now()->subMonths(1)->toDateString(), 'notes' => 'Non-applicable for diesel — remove record or reassign to Bus 18.'],

            // Bus 14 (newer)
            ['v' => '14-bus', 'service_type' => 'oil_change', 'performed_on' => now()->subMonths(1)->toDateString(), 'performed_by' => 'USD444 shop', 'odometer_at_service' => 17500, 'cost_cents' => 9000, 'interval_miles' => 6000, 'interval_months' => 6, 'next_due_miles' => 23500, 'next_due_on' => now()->addMonths(5)->toDateString()],

            // Bus 18 (propane) — requires annual tank inspection
            ['v' => '18-bus', 'service_type' => 'propane_tank_inspection', 'performed_on' => now()->subMonths(10)->toDateString(), 'performed_by' => 'KS Propane Gas Services', 'cost_cents' => 18500, 'interval_months' => 12, 'next_due_on' => now()->addMonths(2)->toDateString()],
            ['v' => '18-bus', 'service_type' => 'oil_change', 'performed_on' => now()->subMonths(3)->toDateString(), 'performed_by' => 'USD444 shop', 'odometer_at_service' => 29500, 'cost_cents' => 8200, 'interval_miles' => 5000, 'interval_months' => 6, 'next_due_miles' => 34500, 'next_due_on' => now()->addMonths(3)->toDateString()],

            // Light vehicles
            ['v' => '3-light_vehicle', 'service_type' => 'tire_rotation', 'performed_on' => now()->subMonths(3)->toDateString(), 'performed_by' => 'Walmart Auto', 'odometer_at_service' => 82200, 'cost_cents' => 3500, 'interval_miles' => 6000, 'next_due_miles' => 88200],
            ['v' => '3-light_vehicle', 'service_type' => 'oil_change',    'performed_on' => now()->subDays(20)->toDateString(),  'performed_by' => 'USD444 shop', 'odometer_at_service' => 87800, 'cost_cents' => 4800, 'interval_miles' => 5000, 'interval_months' => 6, 'next_due_miles' => 92800, 'next_due_on' => now()->subDays(20)->addMonths(6)->toDateString()],
            ['v' => '7-light_vehicle', 'service_type' => 'oil_change',    'performed_on' => now()->subMonths(2)->toDateString(), 'performed_by' => 'USD444 shop', 'odometer_at_service' => 63500, 'cost_cents' => 4500, 'interval_miles' => 5000, 'interval_months' => 6, 'next_due_miles' => 68500, 'next_due_on' => now()->addMonths(4)->toDateString()],
            ['v' => '9-light_vehicle', 'service_type' => 'tire_rotation', 'performed_on' => now()->subMonths(4)->toDateString(), 'performed_by' => 'USD444 shop', 'odometer_at_service' => 109800, 'interval_miles' => 6000, 'next_due_miles' => 115800],
            ['v' => '9-light_vehicle', 'service_type' => 'battery_check', 'performed_on' => now()->subMonths(6)->toDateString(), 'performed_by' => 'USD444 shop', 'interval_months' => 12, 'next_due_on' => now()->addMonths(6)->toDateString()],
        ];

        foreach ($records as $r) {
            $v = $vehicles[$r['v']] ?? null;
            if (! $v) continue;
            $payload = collect($r)->except('v')->toArray();
            MaintenanceRecord::updateOrCreate(
                ['vehicle_id' => $v->id, 'service_type' => $r['service_type'], 'performed_on' => $r['performed_on']],
                array_merge($payload, ['vehicle_id' => $v->id])
            );
        }
    }

    /**
     * Seed ~3 weeks of trip history. Daily routes run M-F; bus athletic and
     * field trips sprinkled in; a couple maintenance and in-progress trips.
     *
     * @param array<string, Vehicle> $vehicles
     * @param array<string, Driver>  $drivers
     * @param array<string, Route>   $routes
     */
    private function trips(array $vehicles, array $drivers, array $routes): void
    {
        $today = CarbonImmutable::now()->startOfDay();
        $start = $today->subDays(21);

        // Daily routes: each route runs M-F for the last 15 school days
        // We'll pick school days (Mon-Fri) in the window and run each route twice (AM, PM)
        $routeOdometers = [
            '5-AM' => 41000, '5-PM' => 41020,
            '1-AM' => 17500, '1-PM' => 17525,
            '3-AM' => 28500, '3-PM' => 28560,
        ];

        $schoolDays = [];
        for ($d = $start; $d->lte($today); $d = $d->addDay()) {
            if ($d->isWeekday()) {
                $schoolDays[] = $d;
            }
        }

        // Keep last 2 weekdays without trips so "in progress" trips feel current
        $completedDays = array_slice($schoolDays, 0, max(0, count($schoolDays) - 1));

        foreach ($completedDays as $day) {
            foreach (['5-AM','5-PM','1-AM','1-PM','3-AM','3-PM'] as $code) {
                $route = $routes[$code];
                $isAm = str_ends_with($code, 'AM');
                [$hour, $min] = $isAm ? [6 + (int)($route->departure_time ? substr($route->departure_time, 0, 2) === '07' ? 1 : 0 : 0), 45] : [15, 15];
                // Use route-scheduled times if present
                $deptParts = explode(':', $route->departure_time ?: ($isAm ? '06:45' : '15:15'));
                $retParts  = explode(':', $route->return_time    ?: ($isAm ? '08:30' : '17:00'));

                $startedAt = $day->setTime((int) $deptParts[0], (int) $deptParts[1]);
                $endedAt   = $day->setTime((int) $retParts[0], (int) $retParts[1]);

                $miles = (int) ($route->estimated_miles ?? 45) + random_int(-2, 3);
                $startOdo = $routeOdometers[$code];
                $endOdo   = $startOdo + $miles;
                $routeOdometers[$code] = $endOdo;

                $eligible   = random_int(28, 45);
                $ineligible = random_int(2, 8);

                Trip::updateOrCreate(
                    [
                        'vehicle_id' => $route->default_vehicle_id,
                        'driver_id'  => $route->default_driver_id,
                        'started_at' => $startedAt,
                    ],
                    [
                        'route_id'        => $route->id,
                        'trip_type'       => Trip::TYPE_DAILY_ROUTE,
                        'purpose'         => "{$route->code} — {$route->name}",
                        'ended_at'        => $endedAt,
                        'start_odometer'  => $startOdo,
                        'end_odometer'    => $endOdo,
                        'passengers'      => $eligible + $ineligible,
                        'riders_eligible' => $eligible,
                        'riders_ineligible' => $ineligible,
                    ]
                );
            }
        }

        // Athletic + activity + field trips sprinkled through the period.
        // (trip_type, vehicle, driver, purpose, days_ago, dep_hr, dur_hr, miles, eligible, ineligible)
        $events = [
            [Trip::TYPE_ATHLETIC,   '12-bus', 'Ortiz',    'Varsity VB @ Hoisington',        2,  16, 6,  120, 0, 14],
            [Trip::TYPE_ATHLETIC,   '12-bus', 'Ortiz',    'JV football @ Sterling',         5,  15, 5,   75, 0, 22],
            [Trip::TYPE_ATHLETIC,   '14-bus', 'Jennings', 'HS track meet @ Lyons',          9,   8, 8,   60, 0, 28],
            [Trip::TYPE_ATHLETIC,   '14-bus', 'Simmons',  'Cross country @ Ellsworth',     12,   7, 7,   85, 0, 18],
            [Trip::TYPE_ATHLETIC,   '12-bus', 'Ortiz',    'Basketball tournament @ Kingman', 15, 13, 9,  110, 0, 15],
            [Trip::TYPE_ACTIVITY,   '12-bus', 'Simmons',  'Scholars Bowl @ Lindsborg',      7,  12, 5,   55, 0,  9],
            [Trip::TYPE_FIELD_TRIP, '14-bus', 'Jennings', '3rd grade to Cosmosphere',      10,   8, 6,   90, 0, 46],
            [Trip::TYPE_FIELD_TRIP, '14-bus', 'Thompson', 'HS chem class to Wichita State', 17,  8, 7,  140, 0, 24],
            [Trip::TYPE_ACTIVITY,   '7-light_vehicle', 'Thompson', 'FFA regional contest', 13,   6, 10, 160, 0, 10],
            [Trip::TYPE_MAINTENANCE,'3-light_vehicle', 'Miller',  'Parts pickup — McPherson',4,   9, 2,   55, 0,  0],
            [Trip::TYPE_MAINTENANCE,'3-light_vehicle', 'Holloway','KHP inspection dropoff',  8,  10, 1,   25, 0,  0],
        ];

        foreach ($events as [$type, $vehKey, $driverLast, $purpose, $daysAgo, $depHour, $durHour, $miles, $elig, $inelig]) {
            $v = $vehicles[$vehKey];
            $d = $drivers[$driverLast];
            $startedAt = $today->subDays($daysAgo)->setTime($depHour, 0);
            $endedAt   = $startedAt->addHours($durHour);
            $startOdo  = (int) $v->odometer_miles - ($miles * 2);
            $endOdo    = $startOdo + $miles;

            Trip::updateOrCreate(
                ['vehicle_id' => $v->id, 'driver_id' => $d->id, 'started_at' => $startedAt],
                [
                    'route_id'          => null,
                    'trip_type'         => $type,
                    'purpose'           => $purpose,
                    'ended_at'          => $endedAt,
                    'start_odometer'    => max(0, $startOdo),
                    'end_odometer'      => max(0, $endOdo),
                    'passengers'        => $elig + $inelig,
                    'riders_eligible'   => $elig,
                    'riders_ineligible' => $inelig,
                ]
            );
        }

        // In-progress trips (started but no ended_at)
        $inProgress = [
            ['12-bus', 'Miller',  Trip::TYPE_DAILY_ROUTE, '5-AM — Route 5 Morning', 2, '5-AM'],
            ['18-bus', 'Holloway', Trip::TYPE_ATHLETIC,    'JV volleyball @ Pratt',  3, null],
        ];
        foreach ($inProgress as [$vehKey, $driverLast, $type, $purpose, $hoursAgo, $routeCode]) {
            $v = $vehicles[$vehKey];
            $d = $drivers[$driverLast];
            $startedAt = CarbonImmutable::now()->subHours($hoursAgo);
            Trip::updateOrCreate(
                ['vehicle_id' => $v->id, 'driver_id' => $d->id, 'started_at' => $startedAt],
                [
                    'route_id'       => $routeCode ? ($routes[$routeCode]->id ?? null) : null,
                    'trip_type'      => $type,
                    'purpose'        => $purpose,
                    'ended_at'       => null,
                    'start_odometer' => (int) $v->odometer_miles,
                ]
            );
        }
    }
}
