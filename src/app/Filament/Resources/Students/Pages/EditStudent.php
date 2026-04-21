<?php

namespace App\Filament\Resources\Students\Pages;

use App\Filament\Resources\Students\StudentResource;
use App\Jobs\GeocodeStudent;
use App\Models\Student;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditStudent extends EditRecord
{
    protected static string $resource = StudentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('geocode')
                ->label('Geocode home address')
                ->icon(Heroicon::OutlinedMapPin)
                ->color('info')
                ->visible(fn (Student $record) => filled($record->home_address))
                ->requiresConfirmation()
                ->modalHeading('Geocode address')
                ->modalDescription(fn (Student $record) => $record->is_geocoded
                    ? 'This will re-geocode the home address and overwrite existing coordinates.'
                    : 'Looks up latitude/longitude for the home address via OpenStreetMap.')
                ->action(function (Student $record) {
                    GeocodeStudent::dispatch($record->id, force: true);
                    Notification::make()
                        ->title('Geocoding queued')
                        ->body('Coordinates will appear after the worker processes the job.')
                        ->success()
                        ->send();
                }),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
