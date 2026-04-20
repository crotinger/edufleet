<?php

namespace App\Filament\Resources\Inspections\Tables;

use App\Models\Inspection;
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

class InspectionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('inspected_on', 'desc')
            ->columns([
                TextColumn::make('vehicle.unit_number')
                    ->label('Unit')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => Inspection::types()[$state] ?? $state)
                    ->sortable(),
                TextColumn::make('inspected_on')->date()->sortable(),
                TextColumn::make('expires_on')
                    ->date()
                    ->sortable()
                    ->color(fn (?string $state) => self::expColor($state))
                    ->description(fn (?string $state) => self::expDescription($state)),
                TextColumn::make('result')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => Inspection::results()[$state] ?? $state)
                    ->color(fn (?string $state) => match ($state) {
                        Inspection::RESULT_PASSED => 'success',
                        Inspection::RESULT_PASSED_WITH_DEFECTS => 'warning',
                        Inspection::RESULT_FAILED => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('inspector_name')->toggleable(),
                TextColumn::make('certificate_number')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('odometer_miles')->numeric()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')->dateTime()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')->options(Inspection::types()),
                SelectFilter::make('result')->options(Inspection::results()),
                Filter::make('expiring_30')
                    ->label('Expires within 30 days')
                    ->toggle()
                    ->query(fn (Builder $query) => $query->whereNotNull('expires_on')
                        ->whereBetween('expires_on', [now()->toDateString(), now()->addDays(30)->toDateString()])),
                Filter::make('expired')
                    ->label('Already expired')
                    ->toggle()
                    ->query(fn (Builder $query) => $query->whereNotNull('expires_on')->where('expires_on', '<', now()->toDateString())),
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

    private static function expColor(?string $state): ?string
    {
        if (! $state) return null;
        $date = \Carbon\Carbon::parse($state);
        return match (true) {
            $date->isPast() => 'danger',
            $date->diffInDays(now(), false) > -30 => 'warning',
            $date->diffInDays(now(), false) > -90 => 'info',
            default => 'success',
        };
    }

    private static function expDescription(?string $state): ?string
    {
        if (! $state) return null;
        $date = \Carbon\Carbon::parse($state);
        if ($date->isPast()) return 'Expired ' . $date->diffForHumans();
        $days = (int) ceil(now()->floatDiffInDays($date));
        return $days <= 30 ? "Expires in {$days} day" . ($days === 1 ? '' : 's') : null;
    }
}
