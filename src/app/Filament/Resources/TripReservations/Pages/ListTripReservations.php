<?php

namespace App\Filament\Resources\TripReservations\Pages;

use App\Filament\Resources\TripReservations\TripReservationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTripReservations extends ListRecords
{
    protected static string $resource = TripReservationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
