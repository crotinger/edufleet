<?php

namespace App\Filament\Resources\TripReservations\Pages;

use App\Filament\Resources\TripReservations\TripReservationResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditTripReservation extends EditRecord
{
    protected static string $resource = TripReservationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
