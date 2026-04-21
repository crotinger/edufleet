<?php

namespace App\Filament\Resources\Students\Tables;

use App\Jobs\GeocodeStudent;
use App\Models\Student;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class StudentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('last_name')
            ->columns([
                TextColumn::make('last_name')
                    ->label('Name')
                    ->formatStateUsing(fn (Student $record) => "{$record->last_name}, {$record->first_name}")
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(),

                TextColumn::make('student_id')
                    ->label('ID')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('grade')
                    ->formatStateUsing(fn (?string $state) => $state ? (Student::grades()[$state] ?? $state) : '—')
                    ->sortable(),

                TextColumn::make('attendance_center')
                    ->label('Center')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('distance_to_school_miles')
                    ->label('Miles')
                    ->numeric(decimalPlaces: 2)
                    ->sortable(),

                TextColumn::make('is_eligible_rider')
                    ->label('Eligibility')
                    ->badge()
                    ->getStateUsing(fn (Student $record) => $record->is_eligible_rider ? 'eligible' : 'ineligible')
                    ->color(fn (string $state) => $state === 'eligible' ? 'success' : 'gray'),

                IconColumn::make('hazardous_route')
                    ->boolean()
                    ->label('Hazard')
                    ->toggleable(),

                IconColumn::make('is_geocoded')
                    ->label('Geo')
                    ->getStateUsing(fn (Student $record) => $record->is_geocoded)
                    ->boolean()
                    ->tooltip(fn (Student $record) => $record->is_geocoded
                        ? ($record->geocoded_at ? 'Geocoded ' . $record->geocoded_at->diffForHumans() : 'Geocoded')
                        : 'No coordinates')
                    ->toggleable(),

                IconColumn::make('active')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('emergency_contact_phone')->label('Emergency')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('grade')->options(Student::grades()),
                SelectFilter::make('attendance_center')
                    ->options(fn () => Student::query()
                        ->whereNotNull('attendance_center')
                        ->distinct()
                        ->orderBy('attendance_center')
                        ->pluck('attendance_center', 'attendance_center')
                        ->all()),
                TernaryFilter::make('active')->default(true),
                Filter::make('eligible')
                    ->label('KSDE eligible only')
                    ->toggle()
                    ->query(fn (Builder $query) => $query->where(function (Builder $q) {
                        $q->where('hazardous_route', true)
                          ->orWhere('distance_to_school_miles', '>=', Student::ELIGIBILITY_THRESHOLD_MILES);
                    })),
                Filter::make('missing_geocode')
                    ->label('Missing coordinates')
                    ->toggle()
                    ->query(fn (Builder $query) => $query->whereNotNull('home_address')
                        ->where(function (Builder $q) {
                            $q->whereNull('home_lat')->orWhereNull('home_lng');
                        })),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('geocode_missing')
                        ->label('Geocode missing coordinates')
                        ->icon('heroicon-o-map-pin')
                        ->color('info')
                        ->requiresConfirmation()
                        ->modalDescription('Queues a geocoding job for each selected student that has an address but no coordinates. Up to ~1 job/second due to the public Nominatim rate limit.')
                        ->action(function (Collection $records) {
                            $count = 0;
                            foreach ($records as $record) {
                                if (filled($record->home_address) && ! $record->is_geocoded) {
                                    GeocodeStudent::dispatch($record->id);
                                    $count++;
                                }
                            }
                            Notification::make()
                                ->title("Queued {$count} geocode job" . ($count === 1 ? '' : 's'))
                                ->success()
                                ->send();
                        }),
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
