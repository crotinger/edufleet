<?php

namespace App\Filament\Imports;

use App\Models\Student;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Number;

class StudentImporter extends Importer
{
    protected static ?string $model = Student::class;

    public static function getColumns(): array
    {
        $grades = array_keys(Student::grades());

        return [
            ImportColumn::make('first_name')
                ->requiredMapping()
                ->example('Jane')
                ->rules(['required', 'max:64']),
            ImportColumn::make('last_name')
                ->requiredMapping()
                ->example('Doe')
                ->rules(['required', 'max:64']),
            ImportColumn::make('student_id')
                ->example('S10245')
                ->rules(['max:32']),
            ImportColumn::make('grade')
                ->example('5')
                ->castStateUsing(fn ($state) => is_string($state) ? trim($state) : $state)
                ->rules(['nullable', 'in:' . implode(',', $grades)]),
            ImportColumn::make('attendance_center')
                ->example('Elementary')
                ->rules(['max:64']),
            ImportColumn::make('home_address')
                ->example('123 Main St, Anytown, KS 67000')
                ->rules(['max:500']),
            ImportColumn::make('home_lat')
                ->numeric()
                ->rules(['nullable', 'numeric', 'between:-90,90']),
            ImportColumn::make('home_lng')
                ->numeric()
                ->rules(['nullable', 'numeric', 'between:-180,180']),
            ImportColumn::make('distance_to_school_miles')
                ->numeric()
                ->example('3.2')
                ->rules(['nullable', 'numeric', 'min:0']),
            ImportColumn::make('hazardous_route')
                ->boolean()
                ->example('false')
                ->rules(['boolean']),
            ImportColumn::make('emergency_contact_name')->rules(['max:128']),
            ImportColumn::make('emergency_contact_phone')->rules(['max:32']),
            ImportColumn::make('medical_notes'),
            ImportColumn::make('active')
                ->boolean()
                ->example('true')
                ->rules(['boolean']),
        ];
    }

    public function resolveRecord(): Student
    {
        $studentId = $this->data['student_id'] ?? null;

        if (filled($studentId)) {
            return Student::withTrashed()->firstOrNew(['student_id' => $studentId]);
        }

        $first = $this->data['first_name'] ?? null;
        $last = $this->data['last_name'] ?? null;
        $center = $this->data['attendance_center'] ?? null;

        if ($first && $last) {
            return Student::withTrashed()->firstOrNew([
                'first_name' => $first,
                'last_name' => $last,
                'attendance_center' => $center,
            ]);
        }

        return new Student();
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your student import has completed: ' . Number::format($import->successful_rows) . ' ' . str('row')->plural($import->successful_rows) . ' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' ' . Number::format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed.';
        }

        return $body;
    }
}
