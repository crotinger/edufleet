<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class RoutePath extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'stops' => 'array',
            'geometry' => 'array',
            'is_active' => 'boolean',
            'distance_meters' => 'integer',
            'duration_seconds' => 'integer',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logUnguarded()
            ->logExcept(['updated_at', 'geometry'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('route_path');
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class);
    }

    protected function distanceMiles(): Attribute
    {
        return Attribute::get(fn () => $this->distance_meters !== null
            ? round($this->distance_meters / 1609.344, 2)
            : null);
    }

    protected function durationMinutes(): Attribute
    {
        return Attribute::get(fn () => $this->duration_seconds !== null
            ? (int) round($this->duration_seconds / 60)
            : null);
    }

    protected function stopCount(): Attribute
    {
        return Attribute::get(fn () => is_array($this->stops) ? count($this->stops) : 0);
    }

    public function markActive(): void
    {
        static::where('route_id', $this->route_id)
            ->where('id', '!=', $this->id)
            ->update(['is_active' => false]);

        $this->is_active = true;
        $this->save();
    }

    protected static function booted(): void
    {
        static::saving(function (RoutePath $path) {
            // Enforce single-active per route at the app level.
            if ($path->is_active) {
                static::where('route_id', $path->route_id)
                    ->when($path->exists, fn ($q) => $q->where('id', '!=', $path->id))
                    ->where('is_active', true)
                    ->update(['is_active' => false]);
            }
        });

        static::saved(function (RoutePath $path) {
            if ($path->is_active && $path->distance_meters !== null) {
                // Keep the parent Route's estimated_miles in sync with the
                // active path — this flows into KSDE reporting when the Trip
                // has no real odometer yet.
                $path->route?->updateQuietly([
                    'estimated_miles' => (int) round($path->distance_meters / 1609.344),
                ]);
            }
        });
    }
}
