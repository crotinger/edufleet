<?php

namespace App\Filament\Resources\TripReservations\Schemas;

use App\Models\Trip;
use App\Models\TripReservation;
use App\Models\Vehicle;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class TripReservationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Key issue')
                    ->description('Scan the key fob barcode OR type its ID to look up the vehicle automatically. You can also pick the vehicle directly.')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('key_barcode')
                                ->label('Key fob barcode')
                                ->maxLength(64)
                                ->placeholder('Scan or type barcode')
                                ->hintIcon(Heroicon::OutlinedInformationCircle)
                                ->hintIconTooltip('Optional. If the vehicle has a key_barcode registered, scanning here auto-selects the vehicle. Hardware barcode scanners type as keyboard input.')
                                ->live(onBlur: true)
                                ->afterStateUpdated(function ($state, callable $set) {
                                    if (! $state) return;
                                    $vehicle = Vehicle::where('key_barcode', trim($state))->first();
                                    if ($vehicle) {
                                        $set('vehicle_id', $vehicle->id);
                                    }
                                }),
                            Select::make('vehicle_id')
                                ->label('Vehicle')
                                ->relationship('vehicle', 'unit_number')
                                ->getOptionLabelFromRecordUsing(fn (Vehicle $v) => "{$v->unit_number} · " . (Vehicle::types()[$v->type] ?? $v->type))
                                ->searchable()
                                ->preload()
                                ->required()
                                ->live()
                                ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                    if (! $state) return;
                                    $vehicle = Vehicle::find($state);
                                    if ($vehicle && $vehicle->key_barcode && blank($get('key_barcode'))) {
                                        $set('key_barcode', $vehicle->key_barcode);
                                    }
                                }),
                        ]),
                    ]),

                Section::make('Trip details')
                    ->description('Filled in by the secretary/director at key-checkout time. The driver confirms at the vehicle when they scan the QR.')
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('planned_trip_type')
                                ->label('Trip type')
                                ->options(Trip::types())
                                ->required()
                                ->native(false)
                                ->default(Trip::TYPE_ATHLETIC),
                            TextInput::make('expected_driver_name')
                                ->label('Expected driver')
                                ->maxLength(128)
                                ->placeholder('e.g. Jane Parent')
                                ->helperText('Free text — volunteer/staff name; no Driver record needed.'),
                            TextInput::make('purpose')
                                ->required()
                                ->maxLength(191)
                                ->placeholder('e.g. JV football @ Sterling')
                                ->columnSpanFull(),
                            TextInput::make('expected_passengers')
                                ->numeric()
                                ->minValue(0)
                                ->maxValue(120),
                            DateTimePicker::make('expected_return_at')
                                ->label('Expected return')
                                ->seconds(false)
                                ->helperText('Optional. Used to auto-expire unclaimed reservations.'),
                        ]),
                    ]),

                Section::make('Internal')
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('source')
                                ->options(TripReservation::sources())
                                ->default(TripReservation::SOURCE_ADMIN_ISSUE)
                                ->required()
                                ->native(false)
                                ->disabled(fn (string $operation) => $operation === 'create')
                                ->dehydrated(),
                            Select::make('status')
                                ->options(TripReservation::statuses())
                                ->default(TripReservation::STATUS_RESERVED)
                                ->required()
                                ->native(false),
                            DateTimePicker::make('issued_at')
                                ->label('Issued at')
                                ->seconds(false)
                                ->required()
                                ->default(now())
                                ->disabled(fn (string $operation) => $operation === 'create')
                                ->dehydrated(),
                        ]),
                        Textarea::make('notes')->rows(2)->columnSpanFull(),
                    ])->collapsible()->collapsed(fn (string $operation) => $operation === 'create'),
            ])
            ->columns(1);
    }
}
