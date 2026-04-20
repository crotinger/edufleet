<?php

namespace App\Filament\Resources\TripRequests\Pages;

use App\Filament\Resources\TripRequests\TripRequestResource;
use App\Models\TripReservation;
use Filament\Resources\Pages\CreateRecord;

class CreateTripRequest extends CreateRecord
{
    protected static string $resource = TripRequestResource::class;

    protected static ?string $title = 'Request a vehicle';

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['source'] = TripReservation::SOURCE_TEACHER_REQUEST;
        $data['status'] = TripReservation::STATUS_REQUESTED;
        $data['requested_by_user_id'] = auth()->id();
        $data['issued_at'] = now();                // creation time; updated when admin approves
        $data['vehicle_id'] = null;                 // admin assigns during approval
        return $data;
    }
}
