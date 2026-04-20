<?php

namespace App\Filament\Resources\MaintenanceRecords\Schemas;

use App\Models\MaintenanceRecord;
use App\Models\Vehicle;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class MaintenanceRecordForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Service')
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('vehicle_id')
                                ->label('Vehicle')
                                ->relationship('vehicle', 'unit_number')
                                ->getOptionLabelFromRecordUsing(fn (Vehicle $v) => "{$v->unit_number} · " . (Vehicle::types()[$v->type] ?? $v->type))
                                ->searchable()
                                ->preload()
                                ->required(),
                            Select::make('service_type')
                                ->options(MaintenanceRecord::serviceTypes())
                                ->required()
                                ->native(false)
                                ->live()
                                ->hintIcon(Heroicon::OutlinedInformationCircle)
                                ->hintIconTooltip('Picking a service type auto-fills the default mile / month intervals. You can still override for this specific record (e.g. a shortened oil change for severe-duty use).')
                                ->afterStateUpdated(function ($state, $set) {
                                    $defaults = MaintenanceRecord::defaultIntervals()[$state] ?? null;
                                    if ($defaults) {
                                        $set('interval_miles', $defaults['miles']);
                                        $set('interval_months', $defaults['months']);
                                    }
                                }),
                            DatePicker::make('performed_on')->required()->default(now()),
                            TextInput::make('performed_by')->label('Performed by / shop')->maxLength(128),
                            TextInput::make('odometer_at_service')->numeric()->minValue(0)->suffix('mi'),
                            TextInput::make('cost_cents')
                                ->label('Cost (cents)')
                                ->numeric()
                                ->minValue(0)
                                ->helperText('Store in cents — e.g. 4500 = $45.00'),
                        ]),
                    ]),

                Section::make('Next due')
                    ->description('Either fill in the intervals (used to auto-calculate) or set the next-due values directly.')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('interval_miles')->numeric()->minValue(0)->suffix('mi'),
                            TextInput::make('interval_months')->numeric()->minValue(0)->maxValue(120)->suffix('months'),
                            TextInput::make('next_due_miles')->numeric()->minValue(0)->suffix('mi'),
                            DatePicker::make('next_due_on'),
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
