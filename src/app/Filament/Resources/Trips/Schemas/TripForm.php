<?php

namespace App\Filament\Resources\Trips\Schemas;

use App\Models\Driver;
use App\Models\Route;
use App\Models\Trip;
use App\Models\Vehicle;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class TripForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Assignment')
                    ->schema([
                        Select::make('route_id')
                            ->label('Route (optional — prefills the fields below)')
                            ->relationship('route', 'code', fn ($query) => $query->where('status', Route::STATUS_ACTIVE)->orderBy('code'))
                            ->getOptionLabelFromRecordUsing(fn (Route $r) => "{$r->code} — {$r->name}")
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                if (! $state) return;
                                $route = Route::find($state);
                                if (! $route) return;
                                if ($route->default_vehicle_id) $set('vehicle_id', $route->default_vehicle_id);
                                if ($route->default_driver_id && ! (auth()->user()?->isDriverOnly() ?? false)) {
                                    $set('driver_id', $route->default_driver_id);
                                }
                                $set('trip_type', Trip::TYPE_DAILY_ROUTE);
                                if (blank($get('purpose'))) {
                                    $set('purpose', "{$route->code} — {$route->name}");
                                }
                                if ($route->default_vehicle_id) {
                                    $lastOdo = Vehicle::find($route->default_vehicle_id)?->odometer_miles;
                                    if ($lastOdo !== null && blank($get('start_odometer'))) {
                                        $set('start_odometer', $lastOdo);
                                    }
                                }
                            }),

                        Grid::make(2)->schema([
                            Select::make('vehicle_id')
                                ->label('Vehicle')
                                ->relationship('vehicle', 'unit_number')
                                ->getOptionLabelFromRecordUsing(fn (Vehicle $v) => "{$v->unit_number} · " . (Vehicle::types()[$v->type] ?? $v->type))
                                ->searchable()
                                ->preload()
                                ->required(),
                            Select::make('driver_id')
                                ->label('Driver')
                                ->relationship('driver', 'last_name')
                                ->getOptionLabelFromRecordUsing(fn (Driver $d) => "{$d->last_name}, {$d->first_name}")
                                ->searchable()
                                ->preload()
                                ->required()
                                ->default(fn () => auth()->user()?->driver?->id)
                                ->disabled(fn () => auth()->user()?->isDriverOnly() ?? false)
                                ->dehydrated(),
                            Select::make('trip_type')
                                ->options(Trip::types())
                                ->required()
                                ->native(false)
                                ->default(Trip::TYPE_DAILY_ROUTE)
                                ->hintIcon(Heroicon::OutlinedInformationCircle)
                                ->hintIconTooltip('Only "Daily route" trips feed the KSDE reimbursement claim. Athletic, field, activity and maintenance trips are tracked for insurance / activity-fund accounting but are not state-reimbursable.'),
                            TextInput::make('purpose')
                                ->maxLength(191)
                                ->placeholder('e.g. "Route 5 AM" or "Varsity VB @ Hoisington"'),
                        ]),
                    ]),

                Section::make('Schedule')
                    ->schema([
                        Grid::make(2)->schema([
                            DateTimePicker::make('started_at')
                                ->seconds(false)
                                ->required()
                                ->default(now()),
                            DateTimePicker::make('ended_at')
                                ->seconds(false)
                                ->helperText('Leave empty while the trip is in progress.')
                                ->after('started_at'),
                        ]),
                    ]),

                Section::make('Odometer')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('start_odometer')
                                ->numeric()
                                ->minValue(0)
                                ->required()
                                ->suffix('mi'),
                            TextInput::make('end_odometer')
                                ->numeric()
                                ->minValue(0)
                                ->suffix('mi')
                                ->helperText('Fill in when the trip finishes.'),
                        ]),
                    ]),

                Section::make('Ridership')
                    ->description('Eligible = students living 2.5+ miles from school (KSDE-reportable). Ineligible = students inside 2.5 mi.')
                    ->schema([
                        Grid::make(3)->schema([
                            TextInput::make('passengers')
                                ->label('Total on board')
                                ->numeric()
                                ->minValue(0)
                                ->maxValue(120)
                                ->hintIcon(Heroicon::OutlinedInformationCircle)
                                ->hintIconTooltip('Raw head count for capacity, liability, and incident reports. Does not drive reimbursement — use eligible/ineligible for that.'),
                            TextInput::make('riders_eligible')
                                ->label('Eligible (2.5 mi+)')
                                ->numeric()
                                ->minValue(0)
                                ->maxValue(120)
                                ->hintIcon(Heroicon::OutlinedInformationCircle)
                                ->hintIconTooltip('Students living 2.5+ miles from their attendance center (or closer if on an approved hazardous route). These riders drive KSDE transportation aid.'),
                            TextInput::make('riders_ineligible')
                                ->label('Ineligible (<2.5 mi)')
                                ->numeric()
                                ->minValue(0)
                                ->maxValue(120)
                                ->hintIcon(Heroicon::OutlinedInformationCircle)
                                ->hintIconTooltip('Students inside 2.5 miles — courtesy riders. Still tracked for ops, but the state does not reimburse for them.'),
                        ]),
                    ])->collapsible(),

                Section::make('Notes')
                    ->schema([
                        Textarea::make('notes')->rows(3)->columnSpanFull(),
                    ])->collapsible()->collapsed(),
            ])
            ->columns(1);
    }
}
