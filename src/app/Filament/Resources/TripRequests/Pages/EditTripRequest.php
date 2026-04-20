<?php

namespace App\Filament\Resources\TripRequests\Pages;

use App\Filament\Resources\TripRequests\TripRequestResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTripRequest extends EditRecord
{
    protected static string $resource = TripRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
