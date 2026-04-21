<?php

namespace App\Filament\Resources\Vehicles\Pages;

use App\Filament\Imports\VehicleImporter;
use App\Filament\Resources\Vehicles\VehicleResource;
use Filament\Actions\CreateAction;
use Filament\Actions\ImportAction;
use Filament\Resources\Pages\ListRecords;

class ListVehicles extends ListRecords
{
    protected static string $resource = VehicleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ImportAction::make()
                ->importer(VehicleImporter::class)
                ->label('Import CSV'),
            CreateAction::make(),
        ];
    }
}
