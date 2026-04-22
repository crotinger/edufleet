<?php

namespace App\Filament\Resources\PostTripInspections\Tables;

use App\Models\Driver;
use App\Models\PostTripInspection;
use App\Models\Vehicle;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PostTripInspectionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('completed_at', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with(['vehicle', 'driver', 'trip']))
            ->columns([
                TextColumn::make('completed_at')
                    ->label('Completed')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('vehicle.unit_number')->label('Unit')->sortable(),
                TextColumn::make('driver')
                    ->label('Driver')
                    ->formatStateUsing(fn (PostTripInspection $r) => $r->driver
                        ? "{$r->driver->last_name}, {$r->driver->first_name}"
                        : ($r->signature_name ?: '—'))
                    ->searchable(query: fn (Builder $query, string $search) => $query
                        ->whereHas('driver', fn ($q) => $q
                            ->where('first_name', 'ilike', "%{$search}%")
                            ->orWhere('last_name', 'ilike', "%{$search}%"))
                        ->orWhere('signature_name', 'ilike', "%{$search}%")),
                TextColumn::make('overall_result')
                    ->label('Result')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => PostTripInspection::overallResultLabels()[$state] ?? $state)
                    ->color(fn (?string $state) => match ($state) {
                        PostTripInspection::RESULT_PASSED => 'success',
                        PostTripInspection::RESULT_PASSED_WITH_DEFECTS => 'warning',
                        PostTripInspection::RESULT_FAILED => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('defect_status')
                    ->label('Defects')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => $state ? (PostTripInspection::defectStatuses()[$state] ?? $state) : '—')
                    ->color(fn (?string $state) => match ($state) {
                        PostTripInspection::DEFECT_OPEN => 'danger',
                        PostTripInspection::DEFECT_ACKNOWLEDGED => 'warning',
                        PostTripInspection::DEFECT_DISPATCHED => 'info',
                        PostTripInspection::DEFECT_CLOSED => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('odometer_miles')->label('Odo')->numeric()->toggleable(),
                TextColumn::make('trip_id')
                    ->label('Trip')
                    ->formatStateUsing(fn (?int $state) => $state ? "#{$state}" : '—')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('overall_result')->options(PostTripInspection::overallResultLabels()),
                SelectFilter::make('defect_status')->options(PostTripInspection::defectStatuses()),
                SelectFilter::make('vehicle_id')
                    ->label('Vehicle')
                    ->options(fn () => Vehicle::orderBy('unit_number')->pluck('unit_number', 'id')->all())
                    ->searchable(),
                SelectFilter::make('driver_id')
                    ->label('Driver')
                    ->options(fn () => Driver::orderBy('last_name')
                        ->get()
                        ->mapWithKeys(fn (Driver $d) => [$d->id => "{$d->last_name}, {$d->first_name}"])
                        ->all())
                    ->searchable(),
                Filter::make('open_defects')
                    ->label('Open defects only')
                    ->toggle()
                    ->query(fn (Builder $query) => $query->where('defect_status', PostTripInspection::DEFECT_OPEN)),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
