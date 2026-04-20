<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Account')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('name')->required()->maxLength(191),
                            TextInput::make('email')
                                ->label('Email')
                                ->email()
                                ->required()
                                ->unique(ignoreRecord: true)
                                ->maxLength(191),
                            TextInput::make('password')
                                ->password()
                                ->revealable()
                                ->dehydrateStateUsing(fn (?string $state) => filled($state) ? Hash::make($state) : null)
                                ->dehydrated(fn (?string $state) => filled($state))
                                ->required(fn (string $operation) => $operation === 'create')
                                ->helperText(fn (string $operation) => $operation === 'edit' ? 'Leave blank to keep current password.' : null)
                                ->minLength(12),
                        ]),
                    ]),

                Section::make('Roles')
                    ->description('Assign one or more roles. super-admin grants all permissions.')
                    ->schema([
                        Select::make('roles')
                            ->relationship('roles', 'name')
                            ->multiple()
                            ->preload()
                            ->options(fn () => Role::pluck('name', 'name'))
                            ->searchable(),
                    ]),
            ])
            ->columns(1);
    }
}
