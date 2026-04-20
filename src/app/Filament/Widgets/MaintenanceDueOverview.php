<?php

namespace App\Filament\Widgets;

use App\Models\MaintenanceRecord;
use App\Models\Vehicle;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class MaintenanceDueOverview extends TableWidget
{
    protected static ?string $heading = 'Maintenance coming due';

    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        $u = auth()->user();
        return $u !== null && $u->hasAnyRole(['super-admin', 'transportation-director', 'mechanic', 'viewer']);
    }

    public function table(Table $table): Table
    {
        $today = now()->toDateString();
        $soon  = now()->addDays(30)->toDateString();
        $milesWindow = 500;

        return $table
            ->query(
                MaintenanceRecord::query()
                    ->select('maintenance_records.*')
                    ->join('vehicles', 'vehicles.id', '=', 'maintenance_records.vehicle_id')
                    ->whereNull('vehicles.deleted_at')
                    ->where(function (Builder $q) use ($today, $soon, $milesWindow) {
                        $q->whereBetween('maintenance_records.next_due_on', [$today, $soon])
                          ->orWhere('maintenance_records.next_due_on', '<', $today)
                          ->orWhereRaw('maintenance_records.next_due_miles IS NOT NULL AND vehicles.odometer_miles >= maintenance_records.next_due_miles - ?', [$milesWindow]);
                    })
                    ->orderByRaw('COALESCE(maintenance_records.next_due_on, CURRENT_DATE + INTERVAL \'100 years\')')
            )
            ->defaultPaginationPageOption(10)
            ->paginated([5, 10, 25])
            ->columns([
                TextColumn::make('vehicle.unit_number')->label('Unit')->sortable()->searchable(),
                TextColumn::make('service_type')
                    ->label('Service')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => MaintenanceRecord::serviceTypes()[$state] ?? $state),
                TextColumn::make('next_due_on')
                    ->label('Due date')
                    ->date()
                    ->color(function (?string $state) {
                        if (! $state) return null;
                        $d = \Carbon\Carbon::parse($state);
                        return match (true) {
                            $d->isPast() => 'danger',
                            $d->diffInDays(now(), false) > -30 => 'warning',
                            default => 'info',
                        };
                    })
                    ->description(function (?string $state) {
                        if (! $state) return null;
                        $d = \Carbon\Carbon::parse($state);
                        if ($d->isPast()) return 'Overdue ' . $d->diffForHumans();
                        $days = (int) ceil(now()->floatDiffInDays($d));
                        return "In {$days} day" . ($days === 1 ? '' : 's');
                    }),
                TextColumn::make('next_due_miles')
                    ->label('Due @ miles')
                    ->numeric()
                    ->description(function (MaintenanceRecord $record) {
                        if (! $record->next_due_miles || ! $record->vehicle) return null;
                        $remaining = $record->next_due_miles - $record->vehicle->odometer_miles;
                        if ($remaining <= 0) return 'Overdue by ' . number_format(abs($remaining)) . ' mi';
                        return number_format($remaining) . ' mi to go';
                    })
                    ->color(function (MaintenanceRecord $record) {
                        if (! $record->next_due_miles || ! $record->vehicle) return null;
                        $remaining = $record->next_due_miles - $record->vehicle->odometer_miles;
                        return match (true) {
                            $remaining <= 0 => 'danger',
                            $remaining <= 500 => 'warning',
                            default => 'info',
                        };
                    }),
                TextColumn::make('performed_on')->label('Last service')->date(),
                TextColumn::make('odometer_at_service')->label('At mi')->numeric()->toggleable(),
            ]);
    }
}
