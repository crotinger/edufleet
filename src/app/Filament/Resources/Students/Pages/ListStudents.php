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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

class ListStudents extends ListRecords
{
    protected static string $resource = StudentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('queue_status')
                ->label('Queue status')
                ->icon(Heroicon::OutlinedQueueList)
                ->color('gray')
                ->modalHeading('Geocoding / queue status')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Close')
                ->modalContent(function () {
                    $pending = 0;
                    try {
                        $pending = Queue::size();
                    } catch (\Throwable $e) {
                        $pending = -1;
                    }
                    $failed = DB::table('failed_jobs')->count();
                    $erroredStudents = Student::whereNotNull('last_geocode_error')->count();
                    $missing = Student::missingGeocode()->count();
                    $recentErrors = Student::whereNotNull('last_geocode_error')
                        ->orderByDesc('last_geocode_attempted_at')
                        ->limit(5)
                        ->get(['first_name', 'last_name', 'home_address', 'last_geocode_error', 'last_geocode_attempted_at']);

                    return view('filament.partials.queue-status', compact('pending', 'failed', 'erroredStudents', 'missing', 'recentErrors'));
                }),
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
