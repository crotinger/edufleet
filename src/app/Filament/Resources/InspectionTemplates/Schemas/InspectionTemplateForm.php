<?php

namespace App\Filament\Resources\InspectionTemplates\Schemas;

use App\Models\InspectionTemplate;
use App\Models\Vehicle;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class InspectionTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()->schema([
                    Grid::make(2)->schema([
                        TextInput::make('name')->required()->maxLength(128),
                        Select::make('inspection_type')
                            ->label('Inspection type')
                            ->options(InspectionTemplate::inspectionTypes())
                            ->default(InspectionTemplate::TYPE_PRE_TRIP)
                            ->required()
                            ->native(false),
                        Select::make('vehicle_type')
                            ->label('Applies to')
                            ->options(['' => 'Any vehicle', ...Vehicle::types()])
                            ->default('')
                            ->dehydrateStateUsing(fn ($state) => $state === '' ? null : $state)
                            ->native(false)
                            ->helperText('Leave blank to apply to any vehicle.'),
                        Toggle::make('active')->default(true)->label('Active'),
                    ]),
                    Textarea::make('description')->rows(2)->columnSpanFull(),
                ])->columnSpanFull(),
            ]);
    }
}
