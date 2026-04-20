<?php

namespace App\Filament\Resources\Drivers\Schemas;

use App\Models\Driver;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class DriverForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identity')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('first_name')->required()->maxLength(64),
                            TextInput::make('last_name')->required()->maxLength(64),
                            TextInput::make('employee_id')->label('Employee ID')->maxLength(32),
                            Select::make('status')
                                ->options(Driver::statuses())
                                ->default(Driver::STATUS_ACTIVE)
                                ->required()
                                ->native(false),
                            TextInput::make('email')->label('Email')->email()->maxLength(191),
                            TextInput::make('phone')->tel()->maxLength(32),
                        ]),
                    ])->columns(1),

                Section::make('Login account (optional)')
                    ->description('Link this driver to a user login. Leave blank if they do not log into edufleet.')
                    ->schema([
                        Select::make('user_id')
                            ->label('Linked user')
                            ->relationship('user', 'name', fn ($query) => $query->orderBy('name'))
                            ->getOptionLabelFromRecordUsing(fn (\App\Models\User $u) => "{$u->name} <{$u->email}>")
                            ->searchable(['name', 'email'])
                            ->preload()
                            ->nullable(),
                    ])->collapsible()->collapsed(),

                Section::make('Employment')
                    ->schema([
                        Grid::make(2)->schema([
                            DatePicker::make('hired_on')->label('Hired on'),
                            DatePicker::make('terminated_on')->label('Terminated on'),
                        ]),
                    ])->columns(1)->collapsible(),

                Section::make('Commercial Driver License (CDL)')
                    ->description('Kansas CDL holders: buses generally need Class B with P + S endorsements.')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('license_number')->label('License number')->maxLength(32),
                            TextInput::make('license_state')->label('State')->default('KS')->maxLength(2)->required(),
                            Select::make('license_class')
                                ->options(Driver::licenseClasses())
                                ->native(false)
                                ->placeholder('Select class')
                                ->hintIcon(Heroicon::OutlinedInformationCircle)
                                ->hintIconTooltip('Kansas CDL classes — A: combination >26k lb towing >10k lb. B: single vehicle >26k lb (most school buses). C: <26k lb with passenger or hazmat endorsement (subs / small vans).'),
                            TextInput::make('restrictions')->maxLength(64)->placeholder('e.g. L, Z, corrective lenses')
                                ->hintIcon(Heroicon::OutlinedInformationCircle)
                                ->hintIconTooltip('Kansas restriction codes from the back of the CDL. Common: L (no air brakes), Z (no full air brakes), 1 (corrective lenses), M (cannot operate Class A passenger vehicle).'),
                            DatePicker::make('license_issued_on'),
                            DatePicker::make('license_expires_on')->required(fn ($get) => filled($get('license_number'))),
                        ]),
                        CheckboxList::make('endorsements')
                            ->options(Driver::endorsementOptions())
                            ->columns(3)
                            ->gridDirection('row')
                            ->hintIcon(Heroicon::OutlinedInformationCircle)
                            ->hintIconTooltip('P is required for any passenger vehicle over 16 (incl. driver). S is required for school buses and also requires a state background check. Most district drivers hold P + S.'),
                    ])->columns(1)->collapsible(),

                Section::make('Medical & Training')
                    ->schema([
                        Grid::make(3)->schema([
                            DatePicker::make('dot_medical_expires_on')
                                ->label('DOT medical card expires')
                                ->hintIcon(Heroicon::OutlinedInformationCircle)
                                ->hintIconTooltip('CDL holders must carry a current DOT physical exam card (aka medical examiner\'s certificate). Max 2-year validity; some conditions shorten to 1 year or 3 months.'),
                            DatePicker::make('first_aid_cpr_expires_on')
                                ->label('First aid / CPR expires')
                                ->hintIcon(Heroicon::OutlinedInformationCircle)
                                ->hintIconTooltip('Red Cross / AHA certification — typically 2 years. KSDE strongly recommends for school bus drivers; some districts require it.'),
                            DatePicker::make('defensive_driving_expires_on')->label('Defensive driving expires'),
                        ]),
                    ])->columns(1)->collapsible(),

                Section::make('Notes')
                    ->schema([
                        Textarea::make('notes')->rows(4)->columnSpanFull(),
                    ])->columns(1)->collapsible()->collapsed(),
            ])
            ->columns(1);
    }
}
