<?php

namespace App\Filament\Imports;

use App\Models\Driver;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Number;

class DriverImporter extends Importer
{
    protected static ?string $model = Driver::class;

    public static function getColumns(): array
    {
        $statuses = array_keys(Driver::statuses());
        $classes = array_keys(Driver::licenseClasses());

        return [
            ImportColumn::make('first_name')
                ->requiredMapping()
                ->example('Jane')
                ->rules(['required', 'max:64']),
            ImportColumn::make('last_name')
                ->requiredMapping()
                ->example('Doe')
                ->rules(['required', 'max:64']),
            ImportColumn::make('employee_id')
                ->example('E1001')
                ->rules(['max:32']),
            ImportColumn::make('email')
                ->rules(['nullable', 'email', 'max:191']),
            ImportColumn::make('phone')
                ->rules(['max:32']),
            ImportColumn::make('hired_on')
                ->rules(['date']),
            ImportColumn::make('terminated_on')
                ->rules(['date']),
            ImportColumn::make('status')
                ->example(Driver::STATUS_ACTIVE)
                ->rules(['in:' . implode(',', $statuses)]),
            ImportColumn::make('license_state')
                ->example('KS')
                ->rules(['max:2']),
            ImportColumn::make('license_number')
                ->example('K01-11-2345')
                ->rules(['max:32']),
            ImportColumn::make('license_class')
                ->example('B')
                ->rules(['in:' . implode(',', $classes)]),
            ImportColumn::make('license_issued_on')
                ->rules(['date']),
            ImportColumn::make('license_expires_on')
                ->rules(['date']),
            ImportColumn::make('endorsements')
                ->castStateUsing(fn ($state) => self::parseEndorsements($state))
                ->example('P|S'),
            ImportColumn::make('restrictions')
                ->rules(['max:64']),
            ImportColumn::make('dot_medical_expires_on')
                ->rules(['date']),
            ImportColumn::make('first_aid_cpr_expires_on')
                ->rules(['date']),
            ImportColumn::make('defensive_driving_expires_on')
                ->rules(['date']),
            ImportColumn::make('notes'),
        ];
    }

    public function resolveRecord(): Driver
    {
        $state = $this->data['license_state'] ?? null;
        $number = $this->data['license_number'] ?? null;
        $employeeId = $this->data['employee_id'] ?? null;

        if ($state && $number) {
            return Driver::withTrashed()->firstOrNew([
                'license_state' => $state,
                'license_number' => $number,
            ]);
        }

        if ($employeeId) {
            return Driver::withTrashed()->firstOrNew(['employee_id' => $employeeId]);
        }

        return new Driver();
    }

    private static function parseEndorsements(mixed $state): ?array
    {
        if (is_array($state)) {
            return $state;
        }
        if (! is_string($state) || trim($state) === '') {
            return null;
        }
        $parts = preg_split('/[\s,|;]+/', strtoupper(trim($state))) ?: [];
        return array_values(array_unique(array_filter($parts)));
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your driver import has completed: ' . Number::format($import->successful_rows) . ' ' . str('row')->plural($import->successful_rows) . ' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' ' . Number::format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed.';
        }

        return $body;
    }
}
