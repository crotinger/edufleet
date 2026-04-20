<?php

namespace App\Filament\Resources\Vehicles\RelationManagers;

use App\Models\MaintenanceRecord;
use App\Models\MaintenanceSchedule;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MaintenanceSchedulesRelationManager extends RelationManager
{
    protected static string $relationship = 'maintenanceSchedules';

    protected static ?string $title = 'Maintenance schedule';

    protected static ?string $recordTitleAttribute = 'service_type';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('service_type')
                    ->options(MaintenanceRecord::serviceTypes())
                    ->required()
                    ->native(false)
                    ->live()
                    ->afterStateUpdated(function ($state, $set) {
                        $defaults = MaintenanceRecord::defaultIntervals()[$state] ?? null;
                        if ($defaults) {
                            $set('interval_miles', $defaults['miles']);
                            $set('interval_months', $defaults['months']);
                        }
                    }),

                Grid::make(2)->schema([
                    TextInput::make('interval_miles')
                        ->numeric()
                        ->minValue(0)
                        ->suffix('mi')
                        ->helperText('e.g. 5,000 for oil changes'),
                    TextInput::make('interval_months')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(120)
                        ->suffix('months')
                        ->helperText('e.g. 12 for annual inspections'),
                ]),

                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true)
                    ->inline(false)
                    ->helperText('Uncheck to pause this item without deleting its history.'),

                Textarea::make('notes')->rows(2)->columnSpanFull(),
            ])
            ->columns(1);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('service_type')
            ->defaultSort('service_type')
            ->columns([
                TextColumn::make('service_type')
                    ->label('Service')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => MaintenanceRecord::serviceTypes()[$state] ?? $state)
                    ->sortable(),
                TextColumn::make('interval_summary')
                    ->label('Cadence')
                    ->placeholder('—'),
                TextColumn::make('next_due')
                    ->label('Next due')
                    ->state(function (MaintenanceSchedule $record) {
                        $p = $record->projection();
                        if (! $p['last_record']) {
                            return 'no history yet';
                        }
                        $parts = [];
                        if ($p['next_due_on']) {
                            $parts[] = $p['next_due_on']->format('M j, Y');
                        }
                        if ($p['next_due_miles'] !== null) {
                            $parts[] = number_format($p['next_due_miles']) . ' mi';
                        }
                        return empty($parts) ? 'n/a' : implode(' · ', $parts);
                    })
                    ->description(function (MaintenanceSchedule $record) {
                        $p = $record->projection();
                        if (! $p['last_record']) return null;
                        $bits = [];
                        if ($p['days_remaining'] !== null) {
                            $d = $p['days_remaining'];
                            $bits[] = $d < 0 ? abs($d) . ' days overdue' : "in {$d} day" . ($d === 1 ? '' : 's');
                        }
                        if ($p['miles_remaining'] !== null) {
                            $m = $p['miles_remaining'];
                            $bits[] = $m < 0 ? number_format(abs($m)) . ' mi overdue' : number_format($m) . ' mi to go';
                        }
                        return $bits ? implode(' · ', $bits) : null;
                    })
                    ->color(function (MaintenanceSchedule $record) {
                        return match ($record->projection()['urgency']) {
                            'overdue' => 'danger',
                            'soon' => 'warning',
                            'upcoming' => 'info',
                            default => 'success',
                        };
                    }),
                TextColumn::make('last_performed')
                    ->label('Last performed')
                    ->state(function (MaintenanceSchedule $record) {
                        $last = $record->lastRecord();
                        if (! $last) return '—';
                        $parts = [$last->performed_on->format('M j, Y')];
                        if ($last->odometer_at_service !== null) {
                            $parts[] = number_format($last->odometer_at_service) . ' mi';
                        }
                        return implode(' @ ', $parts);
                    })
                    ->toggleable(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->toggleable(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
