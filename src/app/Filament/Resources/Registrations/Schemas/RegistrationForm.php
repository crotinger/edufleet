<?php

namespace App\Filament\Resources\Registrations\Schemas;

use App\Models\Vehicle;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class RegistrationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Registration')
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('vehicle_id')
                                ->label('Vehicle')
                                ->relationship('vehicle', 'unit_number')
                                ->getOptionLabelFromRecordUsing(fn (Vehicle $v) => "{$v->unit_number} · " . (Vehicle::types()[$v->type] ?? $v->type))
                                ->searchable()
                                ->preload()
                                ->required(),
                            TextInput::make('state')->default('KS')->maxLength(2)->required(),
                            TextInput::make('plate_number')->maxLength(16),
                            TextInput::make('registration_number')->maxLength(64),
                            DatePicker::make('registered_on'),
                            DatePicker::make('expires_on')->required(),
                            TextInput::make('fee_cents')
                                ->label('Fee (cents)')
                                ->numeric()
                                ->minValue(0)
                                ->helperText('Store amount in cents — e.g., 1500 = $15.00'),
                        ]),
                        Textarea::make('notes')->rows(3)->columnSpanFull(),
                    ]),
            ])
            ->columns(1);
    }
}
