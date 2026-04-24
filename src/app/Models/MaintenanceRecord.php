<?php

namespace App\Models;

use App\Models\Concerns\HasAttachments;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

class MaintenanceRecord extends Model
{
    use HasAttachments;
    use LogsActivity;
    use SoftDeletes;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logUnguarded()
            ->logExcept(['updated_at'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('maintenance');
    }

    public const SERVICE_OIL_CHANGE = 'oil_change';
    public const SERVICE_TIRE_ROTATION = 'tire_rotation';
    public const SERVICE_BRAKE_INSPECTION = 'brake_inspection';
    public const SERVICE_BRAKE_REPLACEMENT = 'brake_replacement';
    public const SERVICE_TRANSMISSION = 'transmission_service';
    public const SERVICE_COOLANT = 'coolant_flush';
    public const SERVICE_BATTERY = 'battery_check';
    public const SERVICE_PROPANE_TANK = 'propane_tank_inspection';
    public const SERVICE_OTHER = 'other';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'performed_on' => 'date',
            'next_due_on' => 'date',
            'odometer_at_service' => 'integer',
            'cost_cents' => 'integer',
            'next_due_miles' => 'integer',
            'interval_miles' => 'integer',
            'interval_months' => 'integer',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public static function serviceTypes(): array
    {
        return [
            self::SERVICE_OIL_CHANGE => 'Oil change',
            self::SERVICE_TIRE_ROTATION => 'Tire rotation',
            self::SERVICE_BRAKE_INSPECTION => 'Brake inspection',
            self::SERVICE_BRAKE_REPLACEMENT => 'Brake replacement',
            self::SERVICE_TRANSMISSION => 'Transmission service',
            self::SERVICE_COOLANT => 'Coolant flush',
            self::SERVICE_BATTERY => 'Battery check',
            self::SERVICE_PROPANE_TANK => 'Propane tank inspection',
            self::SERVICE_OTHER => 'Other',
        ];
    }

    public static function defaultIntervals(): array
    {
        return [
            self::SERVICE_OIL_CHANGE => ['miles' => 5000, 'months' => 6],
            self::SERVICE_TIRE_ROTATION => ['miles' => 6000, 'months' => null],
            self::SERVICE_BRAKE_INSPECTION => ['miles' => 10000, 'months' => 6],
            self::SERVICE_BRAKE_REPLACEMENT => ['miles' => 30000, 'months' => null],
            self::SERVICE_TRANSMISSION => ['miles' => 30000, 'months' => null],
            self::SERVICE_COOLANT => ['miles' => 30000, 'months' => 24],
            self::SERVICE_BATTERY => ['miles' => null, 'months' => 12],
            self::SERVICE_PROPANE_TANK => ['miles' => null, 'months' => 12],
            self::SERVICE_OTHER => ['miles' => null, 'months' => null],
        ];
    }
}
