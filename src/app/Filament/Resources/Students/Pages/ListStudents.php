<?php

namespace App\Filament\Resources\Students\Pages;

use App\Filament\Imports\StudentImporter;
use App\Filament\Resources\Students\StudentResource;
use App\Jobs\GeocodeStudent;
use App\Models\Student;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\ImportAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListStudents extends ListRecords
{
    protected static string $resource = StudentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('geocode_all_missing')
                ->label('Geocode missing addresses')
                ->icon(Heroicon::OutlinedMapPin)
                ->color('info')
                ->visible(fn () => Student::missingGeocode()->exists())
                ->requiresConfirmation()
                ->modalDescription(function () {
                    $n = Student::missingGeocode()->count();
                    return "Queues {$n} geocode job" . ($n === 1 ? '' : 's') . ' against OpenStreetMap Nominatim (≈1 request/sec).';
                })
                ->action(function () {
                    $count = 0;
                    Student::missingGeocode()->lazy()->each(function (Student $student) use (&$count) {
                        GeocodeStudent::dispatch($student->id);
                        $count++;
                    });
                    Notification::make()
                        ->title("Queued {$count} geocode job" . ($count === 1 ? '' : 's'))
                        ->success()
                        ->send();
                }),
            ImportAction::make()
                ->importer(StudentImporter::class)
                ->label('Import CSV'),
            CreateAction::make(),
        ];
    }
}
