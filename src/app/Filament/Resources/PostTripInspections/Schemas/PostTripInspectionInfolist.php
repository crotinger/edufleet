<?php

namespace App\Filament\Resources\PostTripInspections\Schemas;

use App\Models\PostTripInspection;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

class PostTripInspectionInfolist
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
                                ->state(fn (PostTripInspection $r) => $r->driver
                                    ? "{$r->driver->last_name}, {$r->driver->first_name}"
                                    : ($r->signature_name ?: '—')),
                            TextEntry::make('trip_id')
                                ->label('Trip')
                                ->state(fn (PostTripInspection $r) => $r->trip_id ? "#{$r->trip_id}" : '—'),

                            TextEntry::make('completed_at')->label('Completed')->dateTime(),
                            TextEntry::make('odometer_miles')->label('Odometer')->numeric()->placeholder('—'),
                            TextEntry::make('signature_name')->label('Signature')->placeholder('—'),

                            TextEntry::make('overall_result')
                                ->label('Overall')
                                ->badge()
                                ->formatStateUsing(fn (?string $state) => PostTripInspection::overallResultLabels()[$state] ?? $state)
                                ->color(fn (?string $state) => match ($state) {
                                    PostTripInspection::RESULT_PASSED => 'success',
                                    PostTripInspection::RESULT_PASSED_WITH_DEFECTS => 'warning',
                                    PostTripInspection::RESULT_FAILED => 'danger',
                                    default => 'gray',
                                }),
                            TextEntry::make('defect_status')
                                ->label('Defects')
                                ->badge()
                                ->formatStateUsing(fn (?string $state) => $state ? (PostTripInspection::defectStatuses()[$state] ?? $state) : '—'),
                        ]),
                        TextEntry::make('notes')->label('Notes')->placeholder('—')->columnSpanFull(),
                    ])->columns(1),

                Section::make('Items')
                    ->description('All checklist items in the order drivers saw them.')
                    ->schema([
                        View::make('filament.components.post-trip-results-grid')->columnSpanFull(),
                    ])->columns(1),
            ]);
    }
}
