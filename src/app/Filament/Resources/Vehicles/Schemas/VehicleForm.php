<?php

namespace App\Filament\Resources\Vehicles\Schemas;

use App\Models\Vehicle;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

class VehicleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('type')
                    ->options(Vehicle::types())
                    ->default(Vehicle::TYPE_BUS)
                    ->required()
                    ->native(false),
                TextInput::make('unit_number')
                    ->required(),
                TextInput::make('vin'),
                TextInput::make('license_plate'),
                TextInput::make('make'),
                TextInput::make('model'),
                TextInput::make('year')
                    ->numeric(),
                Select::make('fuel_type')
                    ->options(Vehicle::fuelTypes())
                    ->native(false)
                    ->placeholder('Select fuel type'),
                TextInput::make('odometer_miles')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('capacity_passengers')
                    ->numeric(),
                Select::make('status')
                    ->options(Vehicle::statuses())
                    ->default(Vehicle::STATUS_ACTIVE)
                    ->required()
                    ->native(false),
                DatePicker::make('acquired_on'),
                DatePicker::make('retired_on'),
                Textarea::make('notes')
                    ->columnSpanFull(),

                Section::make('Default depot')
                    ->description('Where this vehicle usually sits overnight — often the driver\'s home. Used as the vehicle\'s starting point in the route optimizer; overridable per run.')
                    ->collapsible()
                    ->collapsed(fn ($record) => ! $record?->hasDepot())
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('default_depot_lat')
                                ->label('Latitude')
                                ->numeric()
                                ->step(0.000001)
                                ->live(onBlur: true),
                            TextInput::make('default_depot_lng')
                                ->label('Longitude')
                                ->numeric()
                                ->step(0.000001)
                                ->live(onBlur: true),
                        ]),
                        View::make('filament.components.depot-picker')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
