<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

class Vehicle extends Model
{
    use LogsActivity;
    use SoftDeletes;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logUnguarded()
            ->logExcept(['updated_at'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('vehicle');
    }

    public const TYPE_BUS = 'bus';
    public const TYPE_LIGHT = 'light_vehicle';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_IN_SHOP = 'in_shop';
    public const STATUS_RETIRED = 'retired';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'acquired_on' => 'date',
            'retired_on' => 'date',
            'year' => 'integer',
            'odometer_miles' => 'integer',
            'capacity_passengers' => 'integer',
            'default_depot_lat' => 'float',
            'default_depot_lng' => 'float',
        ];
    }

    public function hasDepot(): bool
    {
        return $this->default_depot_lat !== null && $this->default_depot_lng !== null;
    }

    /** Normalize enum fields to lowercase on save so lookups match the
     *  canonical values in Vehicle::types() / statuses() / fuelTypes()
     *  regardless of how a user typed it or how a CSV import stored it. */
    protected function type(): Attribute
    {
        return Attribute::set(fn ($value) => self::normalizeEnum($value));
    }

    protected function status(): Attribute
    {
        return Attribute::set(fn ($value) => self::normalizeEnum($value));
    }

    protected function fuelType(): Attribute
    {
        return Attribute::set(fn ($value) => self::normalizeEnum($value));
    }

    private static function normalizeEnum(mixed $value): ?string
    {
        if ($value === null) return null;
        $v = strtolower(trim((string) $value));
        return $v === '' ? null : $v;
    }

    public static function types(): array
    {
        return [
            self::TYPE_BUS => 'Bus',
            self::TYPE_LIGHT => 'Light vehicle',
        ];
    }

    public static function statuses(): array
    {
        return [
            self::STATUS_ACTIVE => 'Active',
            self::STATUS_IN_SHOP => 'In shop',
            self::STATUS_RETIRED => 'Retired',
        ];
    }

    public static function fuelTypes(): array
    {
        return [
            'diesel' => 'Diesel',
            'gasoline' => 'Gasoline',
            'propane' => 'Propane',
            'electric' => 'Electric',
            'hybrid' => 'Hybrid',
        ];
    }

    public function inspections(): HasMany
    {
        return $this->hasMany(Inspection::class);
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }

    public function latestInspection(): HasOne
    {
        return $this->hasOne(Inspection::class)->latestOfMany('inspected_on');
    }

    public function latestRegistration(): HasOne
    {
        return $this->hasOne(Registration::class)->latestOfMany('expires_on');
    }

    public function trips(): HasMany
    {
        return $this->hasMany(Trip::class);
    }

    public function maintenanceRecords(): HasMany
    {
        return $this->hasMany(MaintenanceRecord::class);
    }

    public function maintenanceSchedules(): HasMany
    {
        return $this->hasMany(MaintenanceSchedule::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(TripReservation::class);
    }

    public function activeReservation(): HasOne
    {
        return $this->hasOne(TripReservation::class)
            ->whereIn('status', [TripReservation::STATUS_RESERVED, TripReservation::STATUS_CLAIMED])
            ->oldestOfMany('issued_at');
    }

    public function preTripInspections(): HasMany
    {
        return $this->hasMany(PreTripInspection::class);
    }

    public function latestPreTripInspection(): HasOne
    {
        return $this->hasOne(PreTripInspection::class)->latestOfMany('started_at');
    }

    public function postTripInspections(): HasMany
    {
        return $this->hasMany(PostTripInspection::class);
    }

    public function latestPostTripInspection(): HasOne
    {
        return $this->hasOne(PostTripInspection::class)->latestOfMany('completed_at');
    }
}
