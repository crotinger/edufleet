<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

/**
 * Recommended recurring maintenance item for a specific vehicle.
 *
 * Holds the cadence (interval_miles and/or interval_months); the "last performed"
 * and "next due" values are derived from the MaintenanceRecord history at render
 * time so we don't have to write-through on every service completion.
 */
class MaintenanceSchedule extends Model
{
    use LogsActivity;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'interval_miles' => 'integer',
            'interval_months' => 'integer',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logUnguarded()
            ->logExcept(['updated_at'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('maintenance_schedule');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * Find the most recent MaintenanceRecord of this service type for the linked
     * vehicle. Used to compute next-due.
     */
    public function lastRecord(): ?MaintenanceRecord
    {
        return MaintenanceRecord::query()
            ->where('vehicle_id', $this->vehicle_id)
            ->where('service_type', $this->service_type)
            ->latest('performed_on')
            ->first();
    }

    /**
     * Compute projected "next due" date + miles from the last service + intervals.
     *
     * @return array{next_due_on: ?\Carbon\Carbon, next_due_miles: ?int, miles_remaining: ?int, days_remaining: ?int, urgency: string, last_record: ?MaintenanceRecord}
     */
    public function projection(): array
    {
        $last = $this->lastRecord();
        $vehicle = $this->vehicle;

        $nextDueOn = null;
        $nextDueMiles = null;
        if ($last) {
            if ($this->interval_months && $last->performed_on) {
                $nextDueOn = $last->performed_on->copy()->addMonths($this->interval_months);
            }
            if ($this->interval_miles && $last->odometer_at_service !== null) {
                $nextDueMiles = (int) $last->odometer_at_service + $this->interval_miles;
            }
        }

        $daysRemaining = $nextDueOn ? (int) now()->startOfDay()->diffInDays($nextDueOn, false) : null;
        $milesRemaining = ($nextDueMiles !== null && $vehicle?->odometer_miles !== null)
            ? $nextDueMiles - (int) $vehicle->odometer_miles
            : null;

        $urgency = match (true) {
            ($daysRemaining !== null && $daysRemaining < 0) || ($milesRemaining !== null && $milesRemaining < 0) => 'overdue',
            ($daysRemaining !== null && $daysRemaining <= 30) || ($milesRemaining !== null && $milesRemaining <= 500) => 'soon',
            ($daysRemaining !== null && $daysRemaining <= 90) || ($milesRemaining !== null && $milesRemaining <= 2000) => 'upcoming',
            default => 'ok',
        };

        return [
            'next_due_on' => $nextDueOn,
            'next_due_miles' => $nextDueMiles,
            'days_remaining' => $daysRemaining,
            'miles_remaining' => $milesRemaining,
            'urgency' => $urgency,
            'last_record' => $last,
        ];
    }

    protected function intervalSummary(): Attribute
    {
        return Attribute::get(function () {
            $parts = [];
            if ($this->interval_miles) $parts[] = 'every ' . number_format($this->interval_miles) . ' mi';
            if ($this->interval_months) $parts[] = 'every ' . $this->interval_months . ' mo';
            return $parts ? implode(' · ', $parts) : '—';
        });
    }
}
