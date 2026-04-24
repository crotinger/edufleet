<?php

namespace App\Filament\Imports;

use App\Models\Driver;
use App\Models\Route;
use App\Models\Vehicle;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Number;

class RouteImporter extends Importer
{
    protected static ?string $model = Route::class;

    public static function getColumns(): array
    {
        $statuses = array_keys(Route::statuses());

        return [
            ImportColumn::make('code')
                ->requiredMapping()
                ->example('5-AM')
                ->rules(['required', 'max:32']),
            ImportColumn::make('name')
                ->requiredMapping()
                ->example('Route 5 Morning')
                ->rules(['required', 'max:128']),
            ImportColumn::make('description')
                ->example('Rural elementary pickup, east of town'),
            ImportColumn::make('default_vehicle_unit')
                ->label('Default vehicle unit #')
                ->example('12')
                ->helperText('Vehicle unit_number — resolved to default_vehicle_id'),
            ImportColumn::make('default_driver')
                ->label('Default driver')
                ->example('Miller OR Dana Miller OR E1001')
                ->helperText('Last name, "First Last", "Last, First", or employee_id — resolved to default_driver_id'),
            ImportColumn::make('days_of_week')
                ->label('Days of week')
                ->example('mon,tue,wed,thu,fri')
                ->castStateUsing(fn ($state) => self::parseDays($state)),
            ImportColumn::make('departure_time')
                ->example('06:45')
                ->castStateUsing(fn ($state) => self::parseTime($state)),
            ImportColumn::make('return_time')
                ->example('08:30')
                ->castStateUsing(fn ($state) => self::parseTime($state)),
            ImportColumn::make('starting_location')
                ->example('USD bus barn')
                ->rules(['max:191']),
            ImportColumn::make('estimated_miles')
                ->numeric()
                ->example('48')
                ->rules(['nullable', 'integer', 'min:0']),
            ImportColumn::make('status')
                ->example(Route::STATUS_ACTIVE)
                ->rules(['in:' . implode(',', $statuses)]),
        ];
    }

    public function resolveRecord(): Route
    {
        $code = $this->data['code'] ?? null;
        if (filled($code)) {
            return Route::withTrashed()->firstOrNew(['code' => $code]);
        }
        return new Route();
    }

    protected function beforeSave(): void
    {
        // Resolve default_vehicle_unit → default_vehicle_id
        $unit = $this->data['default_vehicle_unit'] ?? null;
        if (filled($unit)) {
            $vehicleId = Vehicle::query()
                ->where('unit_number', (string) $unit)
                ->orderByRaw("CASE type WHEN 'bus' THEN 0 ELSE 1 END")
                ->value('id');
            if ($vehicleId) {
                $this->record->default_vehicle_id = $vehicleId;
            }
        }

        // Resolve default_driver → default_driver_id
        $driver = $this->data['default_driver'] ?? null;
        if (filled($driver)) {
            $driverId = $this->resolveDriverId((string) $driver);
            if ($driverId) {
                $this->record->default_driver_id = $driverId;
            }
        }

        // Default status when missing
        if (empty($this->record->status)) {
            $this->record->status = Route::STATUS_ACTIVE;
        }
    }

    private function resolveDriverId(string $input): ?int
    {
        $input = trim($input);
        if ($input === '') {
            return null;
        }

        // Employee id (trim-tolerant on the DB side)
        $id = Driver::whereRaw('trim(employee_id) = ?', [$input])->value('id');
        if ($id) return $id;

        // Collapse internal whitespace so "Bobbie  Besser" → "bobbie besser"
        // and DB-side trim() catches stored values with trailing whitespace.
        $normalized = preg_replace('/\s+/', ' ', strtolower($input));

        // "Last, First"
        if (str_contains($normalized, ',')) {
            [$last, $first] = array_map('trim', explode(',', $normalized, 2));
            $id = Driver::whereRaw('lower(trim(last_name)) = ?', [$last])
                ->whereRaw('lower(trim(first_name)) = ?', [$first])
                ->value('id');
            if ($id) return $id;
        }

        // "First Last"
        if (str_contains($normalized, ' ')) {
            $parts = explode(' ', $normalized);
            if (count($parts) >= 2) {
                $first = array_shift($parts);
                $last = implode(' ', $parts);
                $id = Driver::whereRaw('lower(trim(first_name)) = ?', [$first])
                    ->whereRaw('lower(trim(last_name)) = ?', [$last])
                    ->value('id');
                if ($id) return $id;
            }
        }

        // Bare last name — works when unique
        $candidates = Driver::whereRaw('lower(trim(last_name)) = ?', [$normalized])->get();
        if ($candidates->count() === 1) {
            return (int) $candidates->first()->id;
        }

        return null;
    }

    /** @return array<int, string> */
    private static function parseDays(mixed $state): array
    {
        if (is_array($state)) {
            return array_values(array_unique(array_filter(array_map(fn ($d) => strtolower(trim((string) $d)), $state))));
        }
        if (! is_string($state) || trim($state) === '') {
            return [];
        }
        $map = [
            'mon' => 'mon', 'monday' => 'mon', 'm' => 'mon',
            'tue' => 'tue', 'tuesday' => 'tue', 'tues' => 'tue', 't' => 'tue',
            'wed' => 'wed', 'wednesday' => 'wed', 'w' => 'wed',
            'thu' => 'thu', 'thursday' => 'thu', 'thur' => 'thu', 'thurs' => 'thu', 'r' => 'thu',
            'fri' => 'fri', 'friday' => 'fri', 'f' => 'fri',
            'sat' => 'sat', 'saturday' => 'sat', 's' => 'sat',
            'sun' => 'sun', 'sunday' => 'sun', 'u' => 'sun',
        ];
        $parts = preg_split('/[\s,|;\/]+/', strtolower(trim($state))) ?: [];
        $out = [];
        foreach ($parts as $p) {
            if ($p === '') continue;
            if (isset($map[$p]) && ! in_array($map[$p], $out, true)) {
                $out[] = $map[$p];
            }
        }
        return $out;
    }

    private static function parseTime(mixed $state): ?string
    {
        if (! is_string($state) || trim($state) === '') {
            return null;
        }
        $state = trim($state);
        // Accept "7:00", "07:00", "7:00 AM", "0700"
        try {
            return \Carbon\Carbon::parse($state)->format('H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your route import has completed: ' . Number::format($import->successful_rows) . ' ' . str('row')->plural($import->successful_rows) . ' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' ' . Number::format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed.';
        }

        return $body;
    }
}
