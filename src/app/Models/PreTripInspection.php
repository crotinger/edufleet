<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class PreTripInspection extends Model
{
    use LogsActivity;
    use SoftDeletes;

    public const RESULT_IN_PROGRESS = 'in_progress';
    public const RESULT_PASSED = 'passed';
    public const RESULT_PASSED_WITH_DEFECTS = 'passed_with_defects';
    public const RESULT_FAILED = 'failed';

    public const DEFECT_OPEN = 'open';
    public const DEFECT_ACKNOWLEDGED = 'acknowledged';
    public const DEFECT_DISPATCHED = 'dispatched';
    public const DEFECT_CLOSED = 'closed';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'odometer_miles' => 'integer',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logUnguarded()
            ->logExcept(['updated_at'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('pre_trip_inspection');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(InspectionTemplate::class, 'inspection_template_id');
    }

    public function results(): HasMany
    {
        return $this->hasMany(PreTripInspectionResult::class);
    }

    public function failedResults(): HasMany
    {
        return $this->hasMany(PreTripInspectionResult::class)->where('result', 'fail');
    }

    public static function overallResultLabels(): array
    {
        return [
            self::RESULT_IN_PROGRESS => 'In progress',
            self::RESULT_PASSED => 'Passed',
            self::RESULT_PASSED_WITH_DEFECTS => 'Passed with defects',
            self::RESULT_FAILED => 'Failed',
        ];
    }

    public static function defectStatuses(): array
    {
        return [
            self::DEFECT_OPEN => 'Open',
            self::DEFECT_ACKNOWLEDGED => 'Acknowledged',
            self::DEFECT_DISPATCHED => 'Maintenance dispatched',
            self::DEFECT_CLOSED => 'Closed',
        ];
    }

    /**
     * Recompute overall_result + defect_status from the current result set
     * and persist. Call after all results are recorded at completion time.
     */
    public function finalize(): void
    {
        $results = $this->results()->get();
        $hasCriticalFail = $results->contains(fn (PreTripInspectionResult $r) => $r->result === 'fail' && $r->was_critical);
        $hasAnyFail = $results->contains(fn (PreTripInspectionResult $r) => $r->result === 'fail');

        $this->overall_result = match (true) {
            $hasCriticalFail => self::RESULT_FAILED,
            $hasAnyFail => self::RESULT_PASSED_WITH_DEFECTS,
            default => self::RESULT_PASSED,
        };

        $this->defect_status = $hasAnyFail ? self::DEFECT_OPEN : self::DEFECT_CLOSED;
        $this->completed_at = $this->completed_at ?? now();
        $this->save();
    }
}
