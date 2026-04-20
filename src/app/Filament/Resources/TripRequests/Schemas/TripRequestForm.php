<?php

namespace App\Filament\Resources\TripRequests\Schemas;

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

class TripRequestForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('When & what')
                    ->description('Tell the transportation office when you need a vehicle and what for. You\'ll get an email when it\'s approved (or denied with a reason).')
                    ->schema([
                        Grid::make(2)->schema([
                            DateTimePicker::make('desired_start_at')
                                ->label('When do you need it?')
                                ->seconds(false)
                                ->required()
                                ->minDate(now()->startOfDay())
                                ->helperText('Include time-of-day, not just the date.'),
                            DateTimePicker::make('expected_return_at')
                                ->label('When will you bring it back?')
                                ->seconds(false)
                                ->required()
                                ->after('desired_start_at'),
                            TextInput::make('purpose')
                                ->label('Purpose')
                                ->required()
                                ->maxLength(191)
                                ->placeholder('e.g. 4th grade field trip to Cosmosphere')
                                ->columnSpanFull(),
                            Select::make('planned_trip_type')
                                ->label('Trip category')
                                ->options(collect(Trip::types())->except(Trip::TYPE_DAILY_ROUTE)->toArray())
                                ->required()
                                ->native(false)
                                ->default(Trip::TYPE_FIELD_TRIP),
                            TextInput::make('expected_passengers')
                                ->label('Expected passengers')
                                ->numeric()
                                ->minValue(0)
                                ->maxValue(120)
                                ->required()
                                ->helperText('For vehicle sizing — include chaperones/adults.'),
                        ]),
                    ]),

                Section::make('Vehicle preference')
                    ->description('Optional. Leave blank if you\'re flexible — the transportation office will assign based on availability.')
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('preferred_vehicle_type')
                                ->label('Preferred type')
                                ->options([
                                    Vehicle::TYPE_BUS => 'Bus (up to 72 passengers)',
                                    Vehicle::TYPE_LIGHT => 'Light vehicle (van / Suburban, up to 12)',
                                ])
                                ->placeholder('No preference')
                                ->native(false)
                                ->hintIcon(Heroicon::OutlinedInformationCircle)
                                ->hintIconTooltip('Buses for group field trips. Light vehicles for small-group activities, errands, or conferences.'),
                            TextInput::make('expected_driver_name')
                                ->label('Who\'s driving?')
                                ->maxLength(128)
                                ->placeholder('Your name or another staff member')
                                ->helperText('Leave blank if you don\'t know yet.'),
                        ]),
                        Textarea::make('notes')
                            ->label('Anything else the transportation office should know?')
                            ->rows(3)
                            ->maxLength(1000)
                            ->columnSpanFull()
                            ->placeholder('e.g. need wheelchair access, leaving from the HS parking lot, etc.'),
                    ])->collapsible(),

                // Admin-only: manage the request workflow
                Section::make('Review (admin)')
                    ->description('Assign a vehicle and approve, or deny with a reason. Teachers cannot see this section on create.')
                    ->visible(fn (string $operation, $record) => $operation === 'edit' && auth()->user()?->hasAnyRole(['super-admin', 'transportation-director']))
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('status')
                                ->options(TripReservation::statuses())
                                ->required()
                                ->native(false),
                            Select::make('vehicle_id')
                                ->label('Assigned vehicle')
                                ->relationship('vehicle', 'unit_number')
                                ->getOptionLabelFromRecordUsing(fn (Vehicle $v) => "{$v->unit_number} · " . (Vehicle::types()[$v->type] ?? $v->type))
                                ->searchable()
                                ->preload(),
                            Textarea::make('denied_reason')
                                ->label('Denial reason (shown to requester)')
                                ->rows(2)
                                ->columnSpanFull()
                                ->placeholder('Only used if status = Denied.'),
                        ]),
                    ])->collapsible(),
            ])
            ->columns(1);
    }
}
