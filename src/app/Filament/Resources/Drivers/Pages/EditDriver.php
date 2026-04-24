<?php

namespace App\Filament\Resources\Drivers\Pages;

use App\Filament\Resources\Drivers\DriverResource;
use App\Models\Attachment;
use App\Models\Driver;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\View;

class EditDriver extends EditRecord
{
    protected static string $resource = DriverResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('downloadBinder')
                ->label('Download DQF binder (ZIP)')
                ->icon(Heroicon::OutlinedArchiveBoxArrowDown)
                ->color('primary')
                ->url(fn (Driver $record) => route('dqf.binder', $record), shouldOpenInNewTab: true),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    public function getFooter(): ?\Illuminate\Contracts\View\View
    {
        return View::make('filament.driver-dqf-panel', [
            'driver' => $this->record,
            'components' => Attachment::dqfComponents(),
            'present' => $this->record->attachments()
                ->whereNotNull('dqf_component')
                ->get()
                ->groupBy('dqf_component'),
        ]);
    }
}
