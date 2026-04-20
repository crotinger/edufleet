<?php

namespace App\Filament\Resources\Vehicles\Pages;

use App\Filament\Resources\Vehicles\VehicleResource;
use App\Models\Vehicle;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\HtmlString;

class EditVehicle extends EditRecord
{
    protected static string $resource = VehicleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('quicktripLabel')
                ->label('Quicktrip label')
                ->icon(Heroicon::OutlinedQrCode)
                ->color('info')
                ->modalHeading('Quicktrip QR label')
                ->modalDescription('Print this label and affix it inside the vehicle cab. The QR points at a signed URL; the PIN blocks URL crawlers.')
                ->modalWidth('md')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Close')
                ->modalContent(function (Vehicle $record): HtmlString {
                    $url = URL::signedRoute('quicktrip', ['vehicle' => $record->id]);
                    $renderer = new ImageRenderer(new RendererStyle(300), new SvgImageBackEnd());
                    $writer = new Writer($renderer);
                    $svg = $writer->writeString($url);
                    // bacon emits an XML prolog we strip to inline the SVG cleanly
                    $svg = preg_replace('/^<\?xml[^>]*\?>\s*/i', '', $svg);
                    $pin = e((string) $record->quicktrip_pin);
                    $unit = e((string) $record->unit_number);
                    $barcode = e((string) ($record->key_barcode ?? '—'));
                    $escapedUrl = e($url);

                    $html = <<<HTML
<div style="text-align: center; padding: 1rem 0;">
    <div style="font-weight: 700; font-size: 1.125rem; margin-bottom: 0.25rem;">edufleet · Unit {$unit}</div>
    <div style="font-size: 0.75rem; color: #64748b; margin-bottom: 1rem; text-transform: uppercase; letter-spacing: 0.04em;">Quick trip log</div>
    <div style="display: inline-block; background: white; padding: 0.5rem; border: 1px solid #e2e8f0; border-radius: 0.5rem;">{$svg}</div>
    <div style="margin-top: 1rem;">
        <div style="font-size: 0.75rem; color: #64748b; text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 0.25rem;">PIN</div>
        <div style="font-family: ui-monospace, Menlo, monospace; font-size: 2rem; font-weight: 800; letter-spacing: 0.3em; color: #1e40af;">{$pin}</div>
    </div>
    <div style="margin-top: 1rem; font-size: 0.6875rem; color: #94a3b8; word-break: break-all; text-align: center;">
        Key barcode: <code>{$barcode}</code>
    </div>
    <div style="margin-top: 0.5rem; font-size: 0.6875rem; color: #94a3b8; word-break: break-all;">
        <a href="{$escapedUrl}" target="_blank" style="color: #2563eb; text-decoration: none;">Open trip form &rarr;</a>
    </div>
</div>
HTML;
                    return new HtmlString($html);
                }),

            Action::make('regeneratePin')
                ->label('Regenerate PIN')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Regenerate the quicktrip PIN?')
                ->modalDescription('The old PIN will immediately stop working. Any printed labels will need to be reprinted.')
                ->visible(fn () => auth()->user()?->hasAnyRole(['super-admin', 'transportation-director']))
                ->action(function (Vehicle $record) {
                    $record->update([
                        'quicktrip_pin' => str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT),
                    ]);
                    Notification::make()
                        ->title('PIN regenerated')
                        ->body('New PIN: ' . $record->fresh()->quicktrip_pin)
                        ->success()
                        ->send();
                }),

            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
