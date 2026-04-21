<?php

namespace App\Filament\Resources\Students\RelationManagers;

use App\Models\Route;
use App\Models\Student;
use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RoutesRelationManager extends RelationManager
{
    protected static string $relationship = 'routes';

    protected static ?string $title = 'Routes';

    protected static ?string $recordTitleAttribute = 'code';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('direction')
                ->options(Student::directions())
                ->default('both')
                ->required()
                ->native(false),
            Textarea::make('notes')->rows(2)->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('code')
            ->columns([
                TextColumn::make('code')->sortable(),
                TextColumn::make('name')->searchable(),
                TextColumn::make('departure_time')->label('Depart'),
                TextColumn::make('return_time')->label('Return'),
                TextColumn::make('pivot.direction')
                    ->label('Direction')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => $state ? (Student::directions()[$state] ?? $state) : '—'),
                TextColumn::make('status')->badge(),
            ])
            ->headerActions([
                AttachAction::make()
                    ->preloadRecordSelect()
                    ->recordSelectSearchColumns(['code', 'name'])
                    ->form(fn (AttachAction $action): array => [
                        $action->getRecordSelect(),
                        Select::make('direction')
                            ->options(Student::directions())
                            ->default('both')
                            ->required()
                            ->native(false),
                        Textarea::make('notes')->rows(2),
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
                DetachAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DetachBulkAction::make(),
                ]),
            ]);
    }
}
