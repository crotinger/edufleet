<?php

namespace App\Filament\Resources\Routes\Tables;

use App\Models\Route;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class RoutesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('code')
            ->columns([
                TextColumn::make('code')->searchable()->sortable(),
                TextColumn::make('name')->searchable()->sortable()->wrap(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => Route::statuses()[$state] ?? $state)
                    ->color(fn (?string $state) => $state === Route::STATUS_ACTIVE ? 'success' : 'gray'),
                TextColumn::make('defaultDriver')
                    ->label('Default driver')
                    ->formatStateUsing(fn (Route $r) => $r->defaultDriver ? "{$r->defaultDriver->last_name}, {$r->defaultDriver->first_name}" : '—'),
                TextColumn::make('defaultVehicle.unit_number')->label('Default unit'),
                TextColumn::make('days_of_week')
                    ->label('Days')
                    ->formatStateUsing(fn ($state) => is_array($state) ? collect($state)->map(fn ($d) => ucfirst($d))->implode(' ') : '—'),
                TextColumn::make('departure_time')
                    ->label('Depart')
                    ->formatStateUsing(fn (?string $state) => $state ? \Carbon\Carbon::parse($state)->format('g:i a') : '—'),
                TextColumn::make('return_time')
                    ->label('Return')
                    ->formatStateUsing(fn (?string $state) => $state ? \Carbon\Carbon::parse($state)->format('g:i a') : '—')
                    ->toggleable(),
                TextColumn::make('estimated_miles')->label('Est mi')->numeric()->toggleable(),
                TextColumn::make('created_at')->dateTime()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->options(Route::statuses()),
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
