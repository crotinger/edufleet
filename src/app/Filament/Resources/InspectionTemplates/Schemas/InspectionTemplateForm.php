<?php

namespace App\Filament\Resources\InspectionTemplates\Schemas;

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
                        Select::make('vehicle_type')
                            ->label('Applies to')
                            ->options(['' => 'Any vehicle', ...Vehicle::types()])
                            ->default('')
                            ->dehydrateStateUsing(fn ($state) => $state === '' ? null : $state)
                            ->native(false)
                            ->helperText('Leave blank to apply to any vehicle — type-scoped templates take precedence.'),
                    ]),
                    Toggle::make('active')->default(true)->label('Active'),
                    Textarea::make('description')->rows(2)->columnSpanFull(),
                ])->columnSpanFull(),
            ]);
    }
}
