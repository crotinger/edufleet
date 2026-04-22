<?php

namespace App\Filament\Resources\PostTripInspections\Pages;

use App\Filament\Resources\PostTripInspections\PostTripInspectionResource;
use App\Models\MaintenanceRecord;
use App\Models\PostTripInspection;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Support\Icons\Heroicon;

class ViewPostTripInspection extends ViewRecord
{
    protected static string $resource = PostTripInspectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('acknowledge')
                ->label('Acknowledge — no work needed')
                ->icon(Heroicon::OutlinedCheck)
                ->color('gray')
                ->visible(fn (PostTripInspection $record) => $record->defect_status === PostTripInspection::DEFECT_OPEN)
                ->requiresConfirmation()
                ->modalHeading('Acknowledge defects without a maintenance record')
                ->modalDescription('Use this when the reported defects don\'t need a shop visit. Marks the inspection as reviewed; no MaintenanceRecord is created.')
                ->action(function (PostTripInspection $record) {
                    $record->update(['defect_status' => PostTripInspection::DEFECT_ACKNOWLEDGED]);
                    Notification::make()->title('Defects acknowledged')->success()->send();
                }),

            Action::make('createMaintenance')
                ->label('Create maintenance record')
                ->icon(Heroicon::OutlinedWrenchScrewdriver)
                ->color('primary')
                ->visible(fn (PostTripInspection $record) => in_array(
                    $record->defect_status,
                    [PostTripInspection::DEFECT_OPEN, PostTripInspection::DEFECT_ACKNOWLEDGED],
                    true,
                ))
                ->modalHeading(fn (PostTripInspection $record) => "Dispatch maintenance for Unit {$record->vehicle?->unit_number}")
                ->modalDescription('Creates a MaintenanceRecord with today\'s date and the post-trip defect summary pre-filled.')
                ->fillForm(fn (PostTripInspection $record) => [
                    'service_type' => MaintenanceRecord::SERVICE_OTHER,
                    'performed_on' => now()->toDateString(),
                    'performed_by' => null,
                    'odometer_at_service' => $record->odometer_miles,
                    'notes' => self::buildMaintenanceNotes($record),
                ])
                ->schema([
                    Grid::make(2)->schema([
                        Select::make('service_type')
                            ->options(MaintenanceRecord::serviceTypes())
                            ->required()
                            ->native(false),
                        DatePicker::make('performed_on')
                            ->label('Target / performed on')
                            ->required()
                            ->helperText('Placeholder until the shop actually does the work.'),
                        TextInput::make('performed_by')
                            ->label('Shop / person')
                            ->maxLength(128)
                            ->placeholder('USD shop, outside vendor, …'),
                        TextInput::make('odometer_at_service')
                            ->label('Odometer at service')
                            ->numeric()
                            ->minValue(0),
                    ]),
                    Textarea::make('notes')
                        ->rows(5)
                        ->required()
                        ->columnSpanFull(),
                ])
                ->action(function (PostTripInspection $record, array $data) {
                    MaintenanceRecord::create([
                        'vehicle_id' => $record->vehicle_id,
                        'service_type' => $data['service_type'],
                        'performed_on' => $data['performed_on'],
                        'performed_by' => $data['performed_by'] ?? null,
                        'odometer_at_service' => $data['odometer_at_service'] ?? null,
                        'notes' => $data['notes'],
                    ]);
                    $record->update(['defect_status' => PostTripInspection::DEFECT_DISPATCHED]);

                    Notification::make()
                        ->title('Maintenance record created')
                        ->body("Linked to Unit {$record->vehicle?->unit_number}.")
                        ->success()
                        ->send();
                }),

            Action::make('reopen')
                ->label('Reopen defects')
                ->icon(Heroicon::OutlinedArrowUturnLeft)
                ->color('warning')
                ->visible(fn (PostTripInspection $record) => in_array(
                    $record->defect_status,
                    [PostTripInspection::DEFECT_ACKNOWLEDGED, PostTripInspection::DEFECT_DISPATCHED, PostTripInspection::DEFECT_CLOSED],
                    true,
                ) && $record->failedResults()->exists())
                ->requiresConfirmation()
                ->modalHeading('Reopen defects')
                ->modalDescription('Moves this inspection back to the "open defects" queue.')
                ->action(function (PostTripInspection $record) {
                    $record->update(['defect_status' => PostTripInspection::DEFECT_OPEN]);
                    Notification::make()->title('Defects reopened')->success()->send();
                }),
        ];
    }

    private static function buildMaintenanceNotes(PostTripInspection $record): string
    {
        $failed = $record->failedResults()->orderBy('id')->get();
        $driver = $record->driver
            ? "{$record->driver->last_name}, {$record->driver->first_name}"
            : ($record->signature_name ?: 'unknown driver');

        $lines = [
            "Dispatched from post-trip inspection #{$record->id} ({$record->completed_at?->format('Y-m-d g:i a')})",
            "Driver: {$driver}",
            "Odometer at inspection: " . ($record->odometer_miles !== null ? number_format($record->odometer_miles) . ' mi' : 'not recorded'),
            '',
            'Failed items:',
        ];

        foreach ($failed as $item) {
            $critical = $item->was_critical ? ' [CRITICAL]' : '';
            $lines[] = "  • [{$item->category_snapshot}]{$critical} {$item->description_snapshot}";
            if ($item->comment) {
                $lines[] = "      — {$item->comment}";
            }
        }

        return implode("\n", $lines);
    }
}
