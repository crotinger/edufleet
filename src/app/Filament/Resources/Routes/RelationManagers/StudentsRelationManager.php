<?php

namespace App\Filament\Resources\Routes\RelationManagers;

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
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class StudentsRelationManager extends RelationManager
{
    protected static string $relationship = 'students';

    protected static ?string $title = 'Roster';

    protected static ?string $recordTitleAttribute = 'last_name';

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
            ->defaultSort('last_name')
            ->columns([
                TextColumn::make('last_name')
                    ->label('Student')
                    ->formatStateUsing(fn (Student $record) => "{$record->last_name}, {$record->first_name}")
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(),
                TextColumn::make('grade')
                    ->formatStateUsing(fn (?string $state) => $state ? (Student::grades()[$state] ?? $state) : '—'),
                TextColumn::make('attendance_center')->label('Center'),
                TextColumn::make('pivot.direction')
                    ->label('Direction')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => $state ? (Student::directions()[$state] ?? $state) : '—'),
                IconColumn::make('is_eligible_rider')
                    ->label('Eligible')
                    ->getStateUsing(fn (Student $record) => $record->is_eligible_rider)
                    ->boolean(),
                TextColumn::make('distance_to_school_miles')
                    ->label('Miles')
                    ->numeric(decimalPlaces: 2),
            ])
            ->filters([
                SelectFilter::make('direction')
                    ->options(Student::directions())
                    ->query(fn ($query, $data) => filled($data['value'] ?? null)
                        ? $query->wherePivot('direction', $data['value'])
                        : $query),
            ])
            ->headerActions([
                AttachAction::make()
                    ->preloadRecordSelect()
                    ->recordSelectSearchColumns(['first_name', 'last_name', 'student_id'])
                    ->multiple()
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
