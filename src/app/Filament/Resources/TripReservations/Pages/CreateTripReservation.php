<?php

namespace App\Filament\Resources\TripReservations\Pages;

use App\Filament\Resources\TripReservations\TripReservationResource;
use App\Models\TripReservation;
use Filament\Resources\Pages\CreateRecord;

class CreateTripReservation extends CreateRecord
{
    protected static string $resource = TripReservationResource::class;

    protected static ?string $title = 'Issue keys';

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Force server-side correct values — these aren't editable on create
        $data['source'] = TripReservation::SOURCE_ADMIN_ISSUE;
        $data['status'] = TripReservation::STATUS_RESERVED;
        $data['issued_at'] = now();
        $data['issued_by_user_id'] = auth()->id();
        return $data;
    }
}
