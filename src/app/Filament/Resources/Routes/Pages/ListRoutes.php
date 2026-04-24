<?php

namespace App\Filament\Resources\Routes\Pages;

use App\Filament\Imports\RouteImporter;
use App\Filament\Resources\Routes\RouteResource;
use Filament\Actions\CreateAction;
use Filament\Actions\ImportAction;
use Filament\Resources\Pages\ListRecords;

class ListRoutes extends ListRecords
{
    protected static string $resource = RouteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ImportAction::make()
                ->importer(RouteImporter::class)
                ->label('Import CSV'),
            CreateAction::make(),
        ];
    }
}
