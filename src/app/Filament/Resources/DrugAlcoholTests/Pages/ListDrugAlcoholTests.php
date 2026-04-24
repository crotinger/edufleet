<?php

namespace App\Filament\Resources\DrugAlcoholTests\Pages;

use App\Filament\Resources\DrugAlcoholTests\DrugAlcoholTestResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDrugAlcoholTests extends ListRecords
{
    protected static string $resource = DrugAlcoholTestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
