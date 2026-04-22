<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class InspectionTemplate extends Model
{
    use LogsActivity;
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logUnguarded()
            ->logExcept(['updated_at'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('inspection_template');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InspectionTemplateItem::class)->orderBy('item_order');
    }

    public function preTripInspections(): HasMany
    {
        return $this->hasMany(PreTripInspection::class);
    }

    /**
     * Best active template for a vehicle. Prefers a template scoped to the
     * vehicle's type, falls back to an unscoped one, else null.
     */
    public static function forVehicle(Vehicle $vehicle): ?self
    {
        return static::query()
            ->where('active', true)
            ->where(function ($q) use ($vehicle) {
                $q->where('vehicle_type', $vehicle->type)
                  ->orWhereNull('vehicle_type');
            })
            ->orderByRaw('CASE WHEN vehicle_type IS NULL THEN 1 ELSE 0 END')
            ->orderBy('id')
            ->first();
    }
}
