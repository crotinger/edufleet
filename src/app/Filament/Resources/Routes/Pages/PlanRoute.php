<?php

namespace App\Filament\Resources\Routes\Pages;

use App\Filament\Resources\Routes\RouteResource;
use App\Models\Route;
use App\Models\RoutePath;
use App\Models\Student;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Support\Icons\Heroicon;

class PlanRoute extends Page
{
    protected static string $resource = RouteResource::class;

    protected string $view = 'filament.resources.routes.pages.plan-route';

    public Route $record;

    public ?RoutePath $activePath = null;

    public function mount(int|string $record): void
    {
        $this->record = Route::findOrFail($record);
        $this->activePath = $this->record->activePath;
    }

    public function getTitle(): string
    {
        return "Plan — {$this->record->code} {$this->record->name}";
    }

    public function getCenterCoordinates(): array
    {
        if ($this->activePath && is_array($this->activePath->stops) && count($this->activePath->stops) > 0) {
            $first = $this->activePath->stops[0];
            if (isset($first['lat'], $first['lng'])) {
                return [(float) $first['lat'], (float) $first['lng']];
            }
        }

        $student = $this->record->students()
            ->whereNotNull('home_lat')
            ->whereNotNull('home_lng')
            ->first();
        if ($student) {
            return [(float) $student->home_lat, (float) $student->home_lng];
        }

        $anyStudent = Student::query()
            ->whereNotNull('home_lat')
            ->whereNotNull('home_lng')
            ->first();
        if ($anyStudent) {
            return [(float) $anyStudent->home_lat, (float) $anyStudent->home_lng];
        }

        // US geographic center as last-resort fallback
        return [39.8283, -98.5795];
    }

    public function save(array $payload): void
    {
        $stops = $payload['stops'] ?? [];
        $versionName = trim($payload['version_name'] ?? '') ?: 'v1';

        if (! is_array($stops)) {
            $stops = [];
        }

        // Re-number stop order to be deterministic on read
        foreach ($stops as $i => &$stop) {
            $stop['order'] = $i;
        }
        unset($stop);

        $path = $this->record->activePath ?? new RoutePath(['route_id' => $this->record->id]);
        $path->version_name = $versionName;
        $path->stops = $stops;
        $path->geometry = $payload['geometry'] ?? null;
        $path->distance_meters = isset($payload['distance_meters']) ? (int) $payload['distance_meters'] : null;
        $path->duration_seconds = isset($payload['duration_seconds']) ? (int) $payload['duration_seconds'] : null;
        $path->profile = $payload['profile'] ?? 'driving';
        $path->is_active = true;
        $path->save();

        $this->activePath = $path->fresh();

        Notification::make()
            ->title('Route saved')
            ->body("Saved " . count($stops) . " stop" . (count($stops) === 1 ? '' : 's') . ".")
            ->success()
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('backToRoute')
                ->label('Back to route')
                ->icon(Heroicon::OutlinedArrowUturnLeft)
                ->color('gray')
                ->url(fn () => RouteResource::getUrl('edit', ['record' => $this->record])),
        ];
    }
}
