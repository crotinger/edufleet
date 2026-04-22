<?php

namespace App\Filament\Resources\PreTripInspections\Pages;

use App\Filament\Resources\PreTripInspections\PreTripInspectionResource;
use Filament\Resources\Pages\ListRecords;

class ListPreTripInspections extends ListRecords
{
    protected static string $resource = PreTripInspectionResource::class;

    // Read-only resource — no Create action.
    protected function getHeaderActions(): array
    {
        return [];
    }
}
