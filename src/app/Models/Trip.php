<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

class Trip extends Model
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
            ->useLogName('trip');
    }

    public const TYPE_DAILY_ROUTE = 'daily_route';
    public const TYPE_ATHLETIC = 'athletic';
    public const TYPE_FIELD_TRIP = 'field_trip';
    public const TYPE_ACTIVITY = 'activity';
    public const TYPE_MAINTENANCE = 'maintenance';
    public const TYPE_OTHER = 'other';

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'approved_at' => 'datetime',
            'start_odometer' => 'integer',
            'end_odometer' => 'integer',
            'passengers' => 'integer',
            'riders_eligible' => 'integer',
            'riders_ineligible' => 'integer',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class);
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(TripReservation::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function preTripInspection(): HasOne
    {
        return $this->hasOne(PreTripInspection::class);
    }

    public static function statuses(): array
    {
        return [
            self::STATUS_PENDING => 'Pending review',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_REJECTED => 'Rejected',
        ];
    }

    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    protected function displayDriverName(): Attribute
    {
        return Attribute::get(function () {
            if ($this->driver) {
                return "{$this->driver->last_name}, {$this->driver->first_name}";
            }
            return $this->driver_name_override ?: '—';
        });
    }

    public static function types(): array
    {
        return [
            self::TYPE_DAILY_ROUTE => 'Daily route',
            self::TYPE_ATHLETIC => 'Athletic',
            self::TYPE_FIELD_TRIP => 'Field trip',
            self::TYPE_ACTIVITY => 'Activity',
            self::TYPE_MAINTENANCE => 'Maintenance shuttle',
            self::TYPE_OTHER => 'Other',
        ];
    }

    protected function miles(): Attribute
    {
        return Attribute::get(fn () => ($this->end_odometer !== null && $this->start_odometer !== null)
            ? max(0, $this->end_odometer - $this->start_odometer)
            : null);
    }

    protected function durationMinutes(): Attribute
    {
        return Attribute::get(fn () => ($this->ended_at && $this->started_at)
            ? (int) $this->started_at->diffInMinutes($this->ended_at)
            : null);
    }

    public function scopeInProgress($query)
    {
        return $query->whereNull('ended_at');
    }

    public function scopeCompleted($query)
    {
        return $query->whereNotNull('ended_at');
    }

    protected static function booted(): void
    {
        // A driver-only user can never log a trip under someone else's name.
        // Enforce it at the model layer so Filament form tampering, direct
        // API calls, and console scripts all hit the same guarantee.
        static::saving(function (Trip $trip): void {
            $user = auth()->user();
            if (! $user || ! method_exists($user, 'isDriverOnly') || ! $user->isDriverOnly()) {
                return;
            }
            $driverId = $user->driver?->id;
            if (! $driverId) {
                throw new \DomainException(
                    'Your user account has the driver role but is not linked to a driver record. Ask an admin to link them.'
                );
            }
            $trip->driver_id = $driverId;
        });

        static::saved(function (Trip $trip): void {
            if (! $trip->wasChanged('end_odometer') || ! $trip->end_odometer) {
                return;
            }
            $vehicle = $trip->vehicle;
            if (! $vehicle) {
                return;
            }
            if ($trip->end_odometer > (int) $vehicle->odometer_miles) {
                $vehicle->forceFill(['odometer_miles' => $trip->end_odometer])->saveQuietly();
            }
        });
    }
}
