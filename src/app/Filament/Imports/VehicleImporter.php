<?php

namespace App\Filament\Imports;

use App\Models\Vehicle;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Number;

class VehicleImporter extends Importer
{
    protected static ?string $model = Vehicle::class;

    public static function getColumns(): array
    {
        $types = array_keys(Vehicle::types());
        $statuses = array_keys(Vehicle::statuses());
        $fuelTypes = array_keys(Vehicle::fuelTypes());

        return [
            ImportColumn::make('type')
                ->requiredMapping()
                ->example($types[0] ?? 'bus')
                ->rules(['required', 'in:' . implode(',', $types)]),
            ImportColumn::make('unit_number')
                ->requiredMapping()
                ->example('12')
                ->rules(['required', 'max:32']),
            ImportColumn::make('make')
                ->example('Blue Bird')
                ->rules(['max:64']),
            ImportColumn::make('model')
                ->example('Vision')
                ->rules(['max:64']),
            ImportColumn::make('year')
                ->numeric()
                ->example('2021')
                ->rules(['integer', 'between:1950,2100']),
            ImportColumn::make('vin')
                ->rules(['max:32']),
            ImportColumn::make('license_plate')
                ->rules(['max:16']),
            ImportColumn::make('fuel_type')
                ->example($fuelTypes[0] ?? 'diesel')
                ->rules(['in:' . implode(',', $fuelTypes)]),
            ImportColumn::make('odometer_miles')
                ->numeric()
                ->example('0')
                ->rules(['integer', 'min:0']),
            ImportColumn::make('capacity_passengers')
                ->numeric()
                ->rules(['integer', 'min:0']),
            ImportColumn::make('status')
                ->example(Vehicle::STATUS_ACTIVE)
                ->rules(['in:' . implode(',', $statuses)]),
            ImportColumn::make('acquired_on')
                ->rules(['date']),
            ImportColumn::make('retired_on')
                ->rules(['date']),
            ImportColumn::make('notes'),
            ImportColumn::make('quicktrip_pin')
                ->rules(['max:16']),
            ImportColumn::make('key_barcode')
                ->rules(['max:64']),
        ];
    }

    public function resolveRecord(): Vehicle
    {
        return Vehicle::withTrashed()->firstOrNew([
            'type' => $this->data['type'] ?? null,
            'unit_number' => $this->data['unit_number'] ?? null,
        ]);
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your vehicle import has completed: ' . Number::format($import->successful_rows) . ' ' . str('row')->plural($import->successful_rows) . ' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' ' . Number::format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed.';
        }

        return $body;
    }
}
