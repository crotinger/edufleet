<?php

namespace App\Filament\Resources\TripReservations\Tables;

use App\Models\Trip;
use App\Models\TripReservation;
use App\Models\Vehicle;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class TripReservationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('issued_at', 'desc')
            ->columns([
                TextColumn::make('issued_at')
                    ->label('Issued')
                    ->dateTime('M j, g:i a')
                    ->sortable(),
                TextColumn::make('vehicle.unit_number')
                    ->label('Unit')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('planned_trip_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => Trip::types()[$state] ?? $state)
                    ->color(fn (?string $state) => match ($state) {
                        Trip::TYPE_DAILY_ROUTE => 'success',
                        Trip::TYPE_ATHLETIC, Trip::TYPE_ACTIVITY => 'info',
                        Trip::TYPE_FIELD_TRIP => 'warning',
                        Trip::TYPE_MAINTENANCE => 'gray',
                        default => 'gray',
                    }),
                TextColumn::make('purpose')->wrap()->searchable(),
                TextColumn::make('expected_driver_name')->label('Driver')->searchable(),
                TextColumn::make('source')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        TripReservation::SOURCE_ADMIN_ISSUE => 'Admin',
                        TripReservation::SOURCE_SELF_SERVICE => 'Self-service',
                        default => $state,
                    })
                    ->color(fn (?string $state) => $state === TripReservation::SOURCE_ADMIN_ISSUE ? 'info' : 'gray'),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => TripReservation::statuses()[$state] ?? $state)
                    ->color(fn (?string $state) => match ($state) {
                        TripReservation::STATUS_RESERVED => 'warning',
                        TripReservation::STATUS_CLAIMED => 'info',
                        TripReservation::STATUS_RETURNED => 'success',
                        TripReservation::STATUS_EXPIRED, TripReservation::STATUS_CANCELLED => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('expected_return_at')
                    ->label('Expected back')
                    ->dateTime('M j, g:i a')
                    ->toggleable(),
                TextColumn::make('issuedBy.name')->label('Issued by')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->options(TripReservation::statuses()),
                SelectFilter::make('source')->options(TripReservation::sources()),
                SelectFilter::make('vehicle_id')
                    ->label('Vehicle')
                    ->relationship('vehicle', 'unit_number')
                    ->getOptionLabelFromRecordUsing(fn (Vehicle $v) => "{$v->unit_number} · " . (Vehicle::types()[$v->type] ?? $v->type))
                    ->searchable()
                    ->preload(),
                TrashedFilter::make(),
            ])
            ->recordActions([
                Action::make('cancel')
                    ->label('Cancel')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (TripReservation $r) => in_array($r->status, [TripReservation::STATUS_RESERVED, TripReservation::STATUS_CLAIMED]))
                    ->action(function (TripReservation $r) {
                        $r->update(['status' => TripReservation::STATUS_CANCELLED]);
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
