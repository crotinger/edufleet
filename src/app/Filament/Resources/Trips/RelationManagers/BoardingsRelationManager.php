<?php

namespace App\Filament\Resources\Trips\RelationManagers;

use App\Models\Student;
use App\Models\Trip;
use App\Models\TripStudentBoarding;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class BoardingsRelationManager extends RelationManager
{
    protected static string $relationship = 'studentBoardings';

    protected static ?string $title = 'Ridership';

    /** Only surface on daily-route bus trips — other trip types don't have a roster. */
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof Trip && $ownerRecord->supportsBoardings();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('student_id')
                ->relationship('student', 'last_name')
                ->getOptionLabelFromRecordUsing(fn (Student $s) => trim("{$s->last_name}, {$s->first_name}")
                    . ($s->grade ? " (Gr {$s->grade})" : ''))
                ->searchable(['first_name', 'last_name', 'student_id'])
                ->preload()
                ->required(),
            Grid::make(2)->schema([
                Toggle::make('boarded')->default(false),
                TextInput::make('stop_name')->label('Stop (optional)')->maxLength(128),
            ]),
            Textarea::make('notes')->rows(2)->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['student' => fn ($q) => $q->withTrashed()]))
            ->defaultSort(function ($query) {
                return $query
                    ->join('students', 'trip_student_boardings.student_id', '=', 'students.id')
                    ->orderBy('students.last_name')
                    ->orderBy('students.first_name')
                    ->select('trip_student_boardings.*');
            })
            ->columns([
                TextColumn::make('student')
                    ->label('Student')
                    ->formatStateUsing(fn (TripStudentBoarding $r) => $r->student
                        ? trim("{$r->student->last_name}, {$r->student->first_name}")
                        : '(deleted)')
                    ->description(fn (TripStudentBoarding $r) => $r->student?->grade ? "Grade {$r->student->grade}" : null)
                    ->searchable(query: fn ($query, string $search) => $query->whereHas('student', fn ($q) => $q
                        ->where('first_name', 'ilike', "%{$search}%")
                        ->orWhere('last_name', 'ilike', "%{$search}%"))),
                IconColumn::make('eligible')
                    ->label('Eligible')
                    ->getStateUsing(fn (TripStudentBoarding $r) => $r->student?->is_eligible_rider ?? false)
                    ->boolean()
                    ->tooltip(fn (TripStudentBoarding $r) => match (true) {
                        $r->student?->hazardous_route => 'Hazardous route — reimbursable',
                        ($r->student?->distance_to_school_miles ?? 0) >= Student::ELIGIBILITY_THRESHOLD_MILES
                            => number_format($r->student->distance_to_school_miles, 2) . ' mi — reimbursable',
                        default => 'Within 2.5 mi — courtesy rider',
                    }),
                TextColumn::make('student.distance_to_school_miles')
                    ->label('Distance')
                    ->formatStateUsing(fn (?float $state) => $state !== null ? number_format($state, 2) . ' mi' : '—')
                    ->toggleable(),
                ToggleColumn::make('boarded')
                    ->label('Boarded')
                    ->afterStateUpdated(function (TripStudentBoarding $record, bool $state) {
                        if ($state && ! $record->boarded_at) {
                            $record->update(['boarded_at' => now()]);
                        }
                    }),
                TextColumn::make('stop_name')->label('Stop')->toggleable(),
                TextColumn::make('boarded_at')->dateTime()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                Action::make('loadFromRoute')
                    ->label('Load route roster')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->color('info')
                    ->visible(fn (RelationManager $livewire) => $livewire->getOwnerRecord() instanceof Trip
                        && $livewire->getOwnerRecord()->route_id !== null)
                    ->requiresConfirmation()
                    ->modalDescription('Creates a ridership row for every student on this trip\'s route who isn\'t already listed. New rows start with Boarded = false — tick the ones that actually rode.')
                    ->action(function (RelationManager $livewire) {
                        $trip = $livewire->getOwnerRecord();
                        if (! $trip instanceof Trip || ! $trip->route_id) {
                            Notification::make()->title('This trip has no route')->warning()->send();
                            return;
                        }
                        $existingStudentIds = $trip->studentBoardings()->pluck('student_id')->all();
                        $rosterStudentIds = $trip->route?->students()->pluck('students.id')->all() ?? [];
                        $toAdd = array_diff($rosterStudentIds, $existingStudentIds);

                        foreach ($toAdd as $studentId) {
                            TripStudentBoarding::create([
                                'trip_id' => $trip->id,
                                'student_id' => $studentId,
                                'boarded' => false,
                            ]);
                        }

                        Notification::make()
                            ->title(count($toAdd) > 0
                                ? 'Added ' . count($toAdd) . ' student' . (count($toAdd) === 1 ? '' : 's') . ' from route roster'
                                : 'Roster already loaded — no new students to add')
                            ->success()
                            ->send();
                    }),
                CreateAction::make()->label('Add student'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('markBoarded')
                        ->label('Mark as boarded')
                        ->icon(Heroicon::OutlinedCheck)
                        ->color('success')
                        ->action(fn ($records) => $records->each->update(['boarded' => true, 'boarded_at' => now()])),
                    BulkAction::make('markNotBoarded')
                        ->label('Mark as not boarded')
                        ->icon(Heroicon::OutlinedXMark)
                        ->color('gray')
                        ->action(fn ($records) => $records->each->update(['boarded' => false, 'boarded_at' => null])),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
