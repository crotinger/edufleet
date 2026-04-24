<?php

namespace App\Filament\Resources\DrugAlcoholTests\Tables;

use App\Models\Driver;
use App\Models\DrugAlcoholTest;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DrugAlcoholTestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('scheduled_for', 'desc')
            ->modifyQueryUsing(fn ($q) => $q->with('driver'))
            ->columns([
                TextColumn::make('driver')
                    ->formatStateUsing(fn (DrugAlcoholTest $r) => $r->driver
                        ? trim("{$r->driver->last_name}, {$r->driver->first_name}")
                        : '—')
                    ->searchable(query: fn (Builder $q, string $s) => $q->whereHas('driver', fn ($dq) => $dq
                        ->where('first_name', 'ilike', "%{$s}%")
                        ->orWhere('last_name', 'ilike', "%{$s}%"))),
                TextColumn::make('test_type')
                    ->badge()
                    ->color(fn (?string $state) => $state === DrugAlcoholTest::TYPE_RANDOM ? 'primary' : 'gray')
                    ->formatStateUsing(fn (?string $state) => DrugAlcoholTest::testTypes()[$state] ?? $state),
                TextColumn::make('test_category')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => DrugAlcoholTest::categories()[$state] ?? $state),
                TextColumn::make('scheduled_for')->label('Scheduled')->date()->sortable(),
                TextColumn::make('completed_on')->label('Completed')->date()->sortable(),
                TextColumn::make('result')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => $state ? (DrugAlcoholTest::results()[$state] ?? $state) : '—')
                    ->color(fn (?string $state) => match ($state) {
                        DrugAlcoholTest::RESULT_NEGATIVE, DrugAlcoholTest::RESULT_DILUTE_NEGATIVE => 'success',
                        DrugAlcoholTest::RESULT_POSITIVE, DrugAlcoholTest::RESULT_REFUSAL,
                        DrugAlcoholTest::RESULT_DILUTE_POSITIVE, DrugAlcoholTest::RESULT_ADULTERATED => 'danger',
                        DrugAlcoholTest::RESULT_CANCELLED => 'warning',
                        default => 'gray',
                    }),
                IconColumn::make('mro_reviewed')->label('MRO')->boolean()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('collection_site')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('reported_on')->date()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('test_type')->options(DrugAlcoholTest::testTypes()),
                SelectFilter::make('test_category')->options(DrugAlcoholTest::categories()),
                SelectFilter::make('result')->options(DrugAlcoholTest::results()),
                SelectFilter::make('driver_id')
                    ->label('Driver')
                    ->searchable()
                    ->options(fn () => Driver::orderBy('last_name')
                        ->get()
                        ->mapWithKeys(fn (Driver $d) => [$d->id => trim("{$d->last_name}, {$d->first_name}")])
                        ->all()),
                Filter::make('open_selections')
                    ->label('Selections awaiting completion')
                    ->toggle()
                    ->query(fn (Builder $q) => $q->whereNotNull('scheduled_for')->whereNull('completed_on')),
                Filter::make('violations')
                    ->label('Violations only (positive / refusal / adulterated)')
                    ->toggle()
                    ->query(fn (Builder $q) => $q->whereIn('result', DrugAlcoholTest::violatingResults())),
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
}
