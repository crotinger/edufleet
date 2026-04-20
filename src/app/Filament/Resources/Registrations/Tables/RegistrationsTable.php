<?php

namespace App\Filament\Resources\Registrations\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RegistrationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('expires_on')
            ->columns([
                TextColumn::make('vehicle.unit_number')->label('Unit')->searchable()->sortable(),
                TextColumn::make('state')->badge()->sortable(),
                TextColumn::make('plate_number')->searchable(),
                TextColumn::make('registration_number')->searchable()->toggleable(),
                TextColumn::make('registered_on')->date()->sortable()->toggleable(),
                TextColumn::make('expires_on')
                    ->date()
                    ->sortable()
                    ->color(fn (?string $state) => self::expColor($state))
                    ->description(fn (?string $state) => self::expDescription($state)),
                TextColumn::make('fee_cents')
                    ->label('Fee')
                    ->formatStateUsing(fn (?int $state) => $state === null ? null : '$' . number_format($state / 100, 2))
                    ->toggleable(),
                TextColumn::make('created_at')->dateTime()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('expiring_30')
                    ->label('Expires within 30 days')
                    ->toggle()
                    ->query(fn (Builder $query) => $query->whereBetween('expires_on', [now()->toDateString(), now()->addDays(30)->toDateString()])),
                Filter::make('expired')
                    ->label('Already expired')
                    ->toggle()
                    ->query(fn (Builder $query) => $query->where('expires_on', '<', now()->toDateString())),
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
