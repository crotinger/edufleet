<?php

namespace App\Filament\Resources\Drivers\Pages;

use App\Filament\Imports\DriverImporter;
use App\Filament\Resources\Drivers\DriverResource;
use Filament\Actions\CreateAction;
use Filament\Actions\ImportAction;
use Filament\Resources\Pages\ListRecords;

class ListDrivers extends ListRecords
{
    protected static string $resource = DriverResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ImportAction::make()
                ->importer(DriverImporter::class)
                ->label('Import CSV'),
            CreateAction::make(),
        ];
    }
}
