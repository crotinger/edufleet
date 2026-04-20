<?php

namespace App\Filament\Resources\MaintenanceRecords\Tables;

use App\Models\MaintenanceRecord;
use App\Models\Vehicle;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MaintenanceRecordsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('performed_on', 'desc')
            ->columns([
                TextColumn::make('performed_on')->date()->sortable(),
                TextColumn::make('vehicle.unit_number')->label('Unit')->searchable()->sortable(),
                TextColumn::make('service_type')
                    ->label('Service')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => MaintenanceRecord::serviceTypes()[$state] ?? $state)
                    ->sortable(),
                TextColumn::make('odometer_at_service')->label('Odometer')->numeric()->toggleable(),
                TextColumn::make('performed_by')->label('Shop / mechanic')->toggleable(),
                TextColumn::make('cost_cents')
                    ->label('Cost')
                    ->formatStateUsing(fn (?int $state) => $state === null ? null : '$' . number_format($state / 100, 2))
                    ->toggleable(),
                TextColumn::make('next_due_miles')
                    ->label('Next @ miles')
                    ->numeric()
                    ->toggleable(),
                TextColumn::make('next_due_on')
                    ->label('Next @ date')
                    ->date()
                    ->toggleable(),
                TextColumn::make('created_at')->dateTime()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('service_type')->options(MaintenanceRecord::serviceTypes()),
                SelectFilter::make('vehicle_id')
                    ->label('Vehicle')
                    ->relationship('vehicle', 'unit_number')
                    ->getOptionLabelFromRecordUsing(fn (Vehicle $v) => "{$v->unit_number} · " . (Vehicle::types()[$v->type] ?? $v->type))
                    ->searchable()
                    ->preload(),
                Filter::make('due_date_30')
                    ->label('Next-due date within 30 days')
                    ->toggle()
                    ->query(fn (Builder $query) => $query->whereNotNull('next_due_on')
                        ->whereBetween('next_due_on', [now()->toDateString(), now()->addDays(30)->toDateString()])),
                Filter::make('overdue_date')
                    ->label('Past next-due date')
                    ->toggle()
                    ->query(fn (Builder $query) => $query->whereNotNull('next_due_on')->where('next_due_on', '<', now()->toDateString())),
                TrashedFilter::make(),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
