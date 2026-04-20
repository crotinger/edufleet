<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

class Inspection extends Model
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
            ->useLogName('inspection');
    }

    public const TYPE_KHP_ANNUAL = 'khp_annual';
    public const TYPE_INTERNAL = 'internal_safety';
    public const TYPE_PRE_TRIP = 'pre_trip';
    public const TYPE_OTHER = 'other';

    public const RESULT_PASSED = 'passed';
    public const RESULT_PASSED_WITH_DEFECTS = 'passed_with_defects';
    public const RESULT_FAILED = 'failed';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'inspected_on' => 'date',
            'expires_on' => 'date',
            'odometer_miles' => 'integer',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public static function types(): array
    {
        return [
            self::TYPE_KHP_ANNUAL => 'KHP annual (school bus)',
            self::TYPE_INTERNAL => 'Internal safety',
            self::TYPE_PRE_TRIP => 'Pre-trip',
            self::TYPE_OTHER => 'Other',
        ];
    }

    public static function results(): array
    {
        return [
            self::RESULT_PASSED => 'Passed',
            self::RESULT_PASSED_WITH_DEFECTS => 'Passed with defects',
            self::RESULT_FAILED => 'Failed',
        ];
    }
}
