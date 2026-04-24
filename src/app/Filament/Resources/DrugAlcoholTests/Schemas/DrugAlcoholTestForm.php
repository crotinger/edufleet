<?php

namespace App\Filament\Resources\DrugAlcoholTests\Schemas;

use App\Models\Driver;
use App\Models\DrugAlcoholTest;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DrugAlcoholTestForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Selection')
                ->description('Who was selected, for what, and why. Use the Outcome section below once the lab reports back.')
                ->schema([
                    Grid::make(2)->schema([
                        Select::make('driver_id')
                            ->label('Driver')
                            ->options(fn () => Driver::orderBy('last_name')
                                ->get()
                                ->mapWithKeys(fn (Driver $d) => [$d->id => trim("{$d->last_name}, {$d->first_name}")])
                                ->all())
                            ->searchable()
                            ->required(),
                        DatePicker::make('scheduled_for')
                            ->label('Scheduled / notified on')
                            ->helperText('When the consortium / DER notified the driver.'),
                        Select::make('test_type')
                            ->options(DrugAlcoholTest::testTypes())
                            ->default(DrugAlcoholTest::TYPE_RANDOM)
                            ->required()
                            ->native(false),
                        Select::make('test_category')
                            ->label('Tested for')
                            ->options(DrugAlcoholTest::categories())
                            ->default(DrugAlcoholTest::CATEGORY_DRUG)
                            ->required()
                            ->native(false),
                    ]),
                ])->columns(1),

            Section::make('Outcome')
                ->description('Fill once the collection is complete and the MRO / BAT has reported.')
                ->schema([
                    Grid::make(2)->schema([
                        DatePicker::make('completed_on')->label('Specimen collected on'),
                        DatePicker::make('reported_on')->label('Result reported on'),
                        Select::make('result')
                            ->options(DrugAlcoholTest::results())
                            ->native(false),
                        TextInput::make('collection_site')
                            ->label('Collection site')
                            ->maxLength(191)
                            ->placeholder('e.g. Hutchinson Clinic, USD419 nurse\'s office'),
                    ]),
                    Toggle::make('mro_reviewed')
                        ->label('Medical Review Officer (MRO) reviewed')
                        ->helperText('Required before a non-negative drug result is final.'),
                    TextInput::make('substances_tested_for')
                        ->label('Substances tested for')
                        ->maxLength(255)
                        ->placeholder('e.g. 5-panel (THC, cocaine, opiates, amphetamines, PCP)'),
                    Textarea::make('notes')->rows(3),
                ])->columns(1)->collapsible(),
        ]);
    }
}
