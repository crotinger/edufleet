<?php

namespace App\Filament\Resources\Drivers\Tables;

use App\Models\Driver;
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

class DriversTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('last_name')
            ->columns([
                TextColumn::make('last_name')
                    ->label('Name')
                    ->formatStateUsing(fn (Driver $record) => "{$record->last_name}, {$record->first_name}")
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(),

                TextColumn::make('employee_id')->label('Emp ID')->searchable()->toggleable(),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => Driver::statuses()[$state] ?? $state)
                    ->color(fn (?string $state) => match ($state) {
                        Driver::STATUS_ACTIVE => 'success',
                        Driver::STATUS_ON_LEAVE => 'warning',
                        Driver::STATUS_INACTIVE => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('license_class')
                    ->label('CDL')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => $state ? "Class {$state}" : '—')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('endorsements')
                    ->formatStateUsing(fn ($state) => is_array($state) ? implode('', $state) : '—')
                    ->toggleable(),

                TextColumn::make('license_expires_on')
                    ->label('License exp.')
                    ->date()
                    ->sortable()
                    ->color(fn (?string $state) => self::expColor($state))
                    ->description(fn (?string $state) => self::expDescription($state)),

                TextColumn::make('dot_medical_expires_on')
                    ->label('DOT med. exp.')
                    ->date()
                    ->sortable()
                    ->color(fn (?string $state) => self::expColor($state))
                    ->description(fn (?string $state) => self::expDescription($state)),

                TextColumn::make('first_aid_cpr_expires_on')->label('First aid/CPR')->date()->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('defensive_driving_expires_on')->label('Defensive driving')->date()->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('phone')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('email')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('hired_on')->date()->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->options(Driver::statuses()),
                SelectFilter::make('license_class')->options(Driver::licenseClasses()),
                Filter::make('license_expiring_30')
                    ->label('License expires within 30 days')
                    ->toggle()
                    ->query(fn (Builder $query) => $query->whereNotNull('license_expires_on')
                        ->whereBetween('license_expires_on', [now()->toDateString(), now()->addDays(30)->toDateString()])),
                Filter::make('medical_expiring_30')
                    ->label('DOT medical expires within 30 days')
                    ->toggle()
                    ->query(fn (Builder $query) => $query->whereNotNull('dot_medical_expires_on')
                        ->whereBetween('dot_medical_expires_on', [now()->toDateString(), now()->addDays(30)->toDateString()])),
                Filter::make('license_expired')
                    ->label('License already expired')
                    ->toggle()
                    ->query(fn (Builder $query) => $query->whereNotNull('license_expires_on')
                        ->where('license_expires_on', '<', now()->toDateString())),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
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
        if (! $state) {
            return null;
        }
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
        if (! $state) {
            return null;
        }
        $date = \Carbon\Carbon::parse($state);
        if ($date->isPast()) {
            return 'Expired ' . $date->diffForHumans();
        }
        $days = (int) ceil(now()->floatDiffInDays($date));
        if ($days <= 30) {
            return "Expires in {$days} day" . ($days === 1 ? '' : 's');
        }
        return null;
    }
}
