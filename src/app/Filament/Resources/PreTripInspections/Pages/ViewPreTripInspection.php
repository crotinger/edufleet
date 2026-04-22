<?php

namespace App\Filament\Resources\PreTripInspections\Pages;

use App\Filament\Resources\PreTripInspections\PreTripInspectionResource;
use Filament\Resources\Pages\ViewRecord;

class ViewPreTripInspection extends ViewRecord
{
    protected static string $resource = PreTripInspectionResource::class;

    // No EditAction — admin actions (Acknowledge / Create maintenance)
    // land in the next commit.
    protected function getHeaderActions(): array
    {
        return [];
    }
}
