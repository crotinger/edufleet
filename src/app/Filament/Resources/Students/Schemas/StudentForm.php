<?php

namespace App\Filament\Resources\Students\Schemas;

use App\Models\Student;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class StudentForm
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
                            TextInput::make('student_id')
                                ->label('Student ID')
                                ->maxLength(32)
                                ->unique(ignoreRecord: true)
                                ->hintIcon(Heroicon::OutlinedInformationCircle)
                                ->hintIconTooltip('District-assigned student identifier, if any.'),
                            Select::make('grade')
                                ->options(Student::grades())
                                ->native(false)
                                ->searchable(),
                        ]),
                    ])->columns(1),

                Section::make('Attendance')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('attendance_center')
                                ->label('Attendance center')
                                ->maxLength(64)
                                ->placeholder('Elementary, MS, HS …'),
                            Toggle::make('active')
                                ->default(true)
                                ->label('Active'),
                        ]),
                    ])->columns(1),

                Section::make('Home & transportation')
                    ->description('Drives KSDE eligibility and — when geocoded — route-stop placement.')
                    ->schema([
                        Textarea::make('home_address')
                            ->rows(2)
                            ->maxLength(500)
                            ->columnSpanFull(),
                        Grid::make(3)->schema([
                            TextInput::make('distance_to_school_miles')
                                ->label('Distance to school (mi)')
                                ->numeric()
                                ->step(0.01)
                                ->minValue(0)
                                ->hintIcon(Heroicon::OutlinedInformationCircle)
                                ->hintIconTooltip('KSDE eligibility: ≥ 2.5 mi reimburses, unless the route is a board-approved hazardous route.'),
                            Toggle::make('hazardous_route')
                                ->label('Hazardous route')
                                ->hintIcon(Heroicon::OutlinedInformationCircle)
                                ->hintIconTooltip('Board-approved hazardous route. If set, the student is reimbursement-eligible regardless of distance.'),
                        ]),
                        Grid::make(2)->schema([
                            TextInput::make('home_lat')
                                ->label('Latitude')
                                ->numeric()
                                ->step(0.000001)
                                ->disabled(fn (Get $get) => ! filled($get('home_address')))
                                ->helperText('Populated by the geocoder — edit only to override.'),
                            TextInput::make('home_lng')
                                ->label('Longitude')
                                ->numeric()
                                ->step(0.000001)
                                ->disabled(fn (Get $get) => ! filled($get('home_address'))),
                        ]),
                    ])->columns(1),

                Section::make('Emergency contact')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('emergency_contact_name')->label('Name')->maxLength(128),
                            TextInput::make('emergency_contact_phone')->label('Phone')->tel()->maxLength(32),
                        ]),
                    ])->columns(1)->collapsible(),

                Section::make('Medical notes')
                    ->schema([
                        Textarea::make('medical_notes')->rows(3)->columnSpanFull(),
                    ])->columns(1)->collapsible()->collapsed(),
            ])
            ->columns(1);
    }
}
