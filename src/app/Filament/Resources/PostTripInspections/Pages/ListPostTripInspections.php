<?php

namespace App\Filament\Resources\PostTripInspections\Pages;

use App\Filament\Resources\PostTripInspections\PostTripInspectionResource;
use Filament\Resources\Pages\ListRecords;

class ListPostTripInspections extends ListRecords
{
    protected static string $resource = PostTripInspectionResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
