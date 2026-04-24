<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Drivers\DriverResource;
use App\Models\Attachment;
use App\Models\Driver;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

class DqfCompliance extends Page
{
    protected string $view = 'filament.pages.dqf-compliance';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $navigationLabel = 'DQF compliance';

    protected static ?string $title = 'Driver Qualification File compliance';

    protected static string|\UnitEnum|null $navigationGroup = 'Admin';

    protected static ?int $navigationSort = 35;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('view_any_driver') ?? false;
    }

    public function getDriverRows(): Collection
    {
        $components = Attachment::dqfComponents();
        $required = collect($components)->where('required', true)->keys();

        return Driver::query()
            ->where('status', Driver::STATUS_ACTIVE)
            ->whereNotNull('license_number')
            ->with(['attachments' => fn ($q) => $q->whereNotNull('dqf_component')])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->map(function (Driver $driver) use ($required, $components) {
                $present = $driver->attachments
                    ->pluck('dqf_component')
                    ->filter()
                    ->unique()
                    ->values();
                $missingRequired = $required->diff($present)->values();
                $presentRequired = $required->intersect($present)->values();
                $pct = $required->count() > 0
                    ? (int) round(($presentRequired->count() / $required->count()) * 100)
                    : 0;

                return (object) [
                    'driver' => $driver,
                    'pct' => $pct,
                    'present_count' => $presentRequired->count(),
                    'required_count' => $required->count(),
                    'missing' => $missingRequired,
                    'present' => $presentRequired,
                    'total_attachments' => $driver->attachments->count(),
                    'component_labels' => collect($components)->map(fn ($m) => $m['label'])->all(),
                ];
            });
    }

    public function getDriverEditUrl(Driver $driver): string
    {
        return DriverResource::getUrl('edit', ['record' => $driver]);
    }

    public function getBinderUrl(Driver $driver): string
    {
        return route('dqf.binder', $driver);
    }
}
