<?php

namespace App\Filament\Resources\DrugAlcoholTests\Pages;

use App\Filament\Resources\DrugAlcoholTests\DrugAlcoholTestResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditDrugAlcoholTest extends EditRecord
{
    protected static string $resource = DrugAlcoholTestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
