<?php

namespace App\Filament\Resources\Inspections\Schemas;

use App\Models\Inspection;
use App\Models\Vehicle;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class InspectionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Inspection')
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('vehicle_id')
                                ->label('Vehicle')
                                ->relationship('vehicle', 'unit_number')
                                ->getOptionLabelFromRecordUsing(fn (Vehicle $v) => "{$v->unit_number} · " . (Vehicle::types()[$v->type] ?? $v->type))
                                ->searchable()
                                ->preload()
                                ->required(),
                            Select::make('type')
                                ->options(Inspection::types())
                                ->required()
                                ->native(false)
                                ->hintIcon(Heroicon::OutlinedInformationCircle)
                                ->hintIconTooltip('KHP annual = required Kansas Highway Patrol inspection for school buses (annual, state-issued sticker). Internal = our own shop safety check. Pre-trip = driver\'s daily walk-around.'),
                            DatePicker::make('inspected_on')->required(),
                            DatePicker::make('expires_on'),
                            Select::make('result')
                                ->options(Inspection::results())
                                ->required()
                                ->native(false),
                            TextInput::make('inspector_name')->maxLength(128),
                            TextInput::make('certificate_number')->maxLength(64),
                            TextInput::make('odometer_miles')->numeric()->minValue(0),
                        ]),
                        Textarea::make('notes')->rows(3)->columnSpanFull(),
                    ]),
            ])
            ->columns(1);
    }
}
