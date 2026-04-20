<?php

namespace App\Filament\Resources\Routes\Schemas;

use App\Models\Driver;
use App\Models\Route;
use App\Models\Vehicle;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class RouteForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Route')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('code')
                                ->required()
                                ->maxLength(32)
                                ->unique(ignoreRecord: true)
                                ->placeholder('e.g. 5-AM'),
                            TextInput::make('name')->required()->maxLength(128)->placeholder('e.g. Route 5 Morning'),
                            Select::make('status')
                                ->options(Route::statuses())
                                ->default(Route::STATUS_ACTIVE)
                                ->required()
                                ->native(false),
                            TextInput::make('estimated_miles')->numeric()->minValue(0)->suffix('mi'),
                        ]),
                        Textarea::make('description')->rows(2)->columnSpanFull(),
                    ]),

                Section::make('Defaults')
                    ->description('These prefill when a dispatcher creates a trip on this route.')
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('default_vehicle_id')
                                ->label('Default vehicle')
                                ->relationship('defaultVehicle', 'unit_number')
                                ->getOptionLabelFromRecordUsing(fn (Vehicle $v) => "{$v->unit_number} · " . (Vehicle::types()[$v->type] ?? $v->type))
                                ->searchable()
                                ->preload(),
                            Select::make('default_driver_id')
                                ->label('Default driver')
                                ->relationship('defaultDriver', 'last_name')
                                ->getOptionLabelFromRecordUsing(fn (Driver $d) => "{$d->last_name}, {$d->first_name}")
                                ->searchable()
                                ->preload(),
                        ]),
                    ])->collapsible(),

                Section::make('Schedule')
                    ->schema([
                        Grid::make(3)->schema([
                            TimePicker::make('departure_time')->seconds(false),
                            TimePicker::make('return_time')->seconds(false),
                            TextInput::make('starting_location')->maxLength(191)->placeholder('e.g. USD444 bus barn'),
                        ]),
                        CheckboxList::make('days_of_week')
                            ->label('Days of week')
                            ->options(Route::dayOptions())
                            ->columns(7)
                            ->gridDirection('row')
                            ->hintIcon(Heroicon::OutlinedInformationCircle)
                            ->hintIconTooltip('Which days this route runs during the school year. Holidays / breaks / weather closures aren\'t modeled here — just the weekly pattern. Don\'t generate trips on closed days.'),
                    ])->collapsible(),
            ])
            ->columns(1);
    }
}
