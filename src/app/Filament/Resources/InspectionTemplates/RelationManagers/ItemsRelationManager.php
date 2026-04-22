<?php

namespace App\Filament\Resources\InspectionTemplates\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Checklist items';

    protected static ?string $recordTitleAttribute = 'description';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(2)->schema([
                TextInput::make('category')
                    ->required()
                    ->maxLength(64)
                    ->placeholder('e.g. Brakes, Lights, Tires'),
                Toggle::make('is_critical')
                    ->label('Critical (blocks trip on failure)')
                    ->default(false),
            ]),
            Textarea::make('description')
                ->required()
                ->maxLength(255)
                ->rows(2)
                ->columnSpanFull()
                ->placeholder('Clear instruction, e.g. "Headlights — high and low beam"'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->reorderable('item_order')
            ->defaultSort('item_order')
            ->columns([
                TextColumn::make('category')
                    ->badge()
                    ->color('gray')
                    ->sortable(),
                TextColumn::make('description')
                    ->wrap()
                    ->searchable(),
                IconColumn::make('is_critical')
                    ->label('Critical')
                    ->boolean()
                    ->alignCenter(),
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
