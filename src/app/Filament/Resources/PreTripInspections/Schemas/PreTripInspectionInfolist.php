<?php

namespace App\Filament\Resources\PreTripInspections\Schemas;

use App\Models\PreTripInspection;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

class PreTripInspectionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Inspection')
                    ->schema([
                        Grid::make(3)->schema([
                            TextEntry::make('vehicle.unit_number')->label('Vehicle'),
                            TextEntry::make('driver')
                                ->label('Driver')
                                ->state(fn (PreTripInspection $r) => $r->driver
                                    ? "{$r->driver->last_name}, {$r->driver->first_name}"
                                    : '—'),
                            TextEntry::make('trip_id')
                                ->label('Trip')
                                ->state(fn (PreTripInspection $r) => $r->trip_id ? "#{$r->trip_id}" : '—'),

                            TextEntry::make('started_at')->label('Started')->dateTime(),
                            TextEntry::make('completed_at')->label('Completed')->dateTime()->placeholder('—'),
                            TextEntry::make('odometer_miles')->label('Odometer')->numeric()->placeholder('—'),

                            TextEntry::make('overall_result')
                                ->label('Overall')
                                ->badge()
                                ->formatStateUsing(fn (?string $state) => PreTripInspection::overallResultLabels()[$state] ?? $state)
                                ->color(fn (?string $state) => match ($state) {
                                    PreTripInspection::RESULT_PASSED => 'success',
                                    PreTripInspection::RESULT_PASSED_WITH_DEFECTS => 'warning',
                                    PreTripInspection::RESULT_FAILED => 'danger',
                                    default => 'gray',
                                }),
                            TextEntry::make('defect_status')
                                ->label('Defects')
                                ->badge()
                                ->formatStateUsing(fn (?string $state) => $state ? (PreTripInspection::defectStatuses()[$state] ?? $state) : '—'),
                            TextEntry::make('signature_name')->label('Signature')->placeholder('—'),
                        ]),

                        TextEntry::make('notes')->label('Notes')->placeholder('—')->columnSpanFull(),
                    ])->columns(1),

                Section::make('Items')
                    ->description('All checklist items in the order they were presented to the driver.')
                    ->schema([
                        View::make('filament.components.pre-trip-results-grid')->columnSpanFull(),
                    ])->columns(1),
            ]);
    }
}
