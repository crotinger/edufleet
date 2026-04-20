<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

class TripReservation extends Model
{
    use LogsActivity;
    use SoftDeletes;

    public const SOURCE_ADMIN_ISSUE = 'admin_issue';
    public const SOURCE_SELF_SERVICE = 'self_service';
    public const SOURCE_TEACHER_REQUEST = 'teacher_request';

    // Request lifecycle:
    //   requested → (admin approves) → reserved → claimed → returned
    //   requested → (admin denies) → denied
    //   any active → cancelled (manually), or → expired (aged out)
    public const STATUS_REQUESTED = 'requested';
    public const STATUS_RESERVED = 'reserved';
    public const STATUS_CLAIMED = 'claimed';
    public const STATUS_RETURNED = 'returned';
    public const STATUS_DENIED = 'denied';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELLED = 'cancelled';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'expected_return_at' => 'datetime',
            'desired_start_at' => 'datetime',
            'denied_at' => 'datetime',
            'expected_passengers' => 'integer',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logUnguarded()
            ->logExcept(['updated_at'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('reservation');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by_user_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function deniedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'denied_by_user_id');
    }

    /**
     * When a teacher request is approved across multiple vehicles, every leg
     * shares a split_group_id UUID. This scope returns all other legs for the
     * same group (not including the current record).
     */
    public function splitSiblings(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(self::class, 'split_group_id', 'split_group_id')
            ->where('id', '!=', $this->id);
    }

    /**
     * All legs (including self) of a split group.
     *
     * @return \Illuminate\Support\Collection<int, self>
     */
    public function allSplitLegs(): \Illuminate\Support\Collection
    {
        if (! $this->split_group_id) {
            return collect([$this]);
        }
        return self::where('split_group_id', $this->split_group_id)->orderBy('id')->get();
    }

    public static function sources(): array
    {
        return [
            self::SOURCE_ADMIN_ISSUE => 'Admin-issued (keys checkout)',
            self::SOURCE_SELF_SERVICE => 'Self-service (from QR)',
            self::SOURCE_TEACHER_REQUEST => 'Teacher request',
        ];
    }

    public static function statuses(): array
    {
        return [
            self::STATUS_REQUESTED => 'Requested (awaiting approval)',
            self::STATUS_RESERVED => 'Reserved (approved, keys not yet out)',
            self::STATUS_CLAIMED => 'Claimed (trip started)',
            self::STATUS_RETURNED => 'Returned (trip complete)',
            self::STATUS_DENIED => 'Denied',
            self::STATUS_EXPIRED => 'Expired',
            self::STATUS_CANCELLED => 'Cancelled',
        ];
    }

    public function scopeActiveForVehicle($query, int $vehicleId)
    {
        return $query->where('vehicle_id', $vehicleId)
            ->whereIn('status', [self::STATUS_RESERVED, self::STATUS_CLAIMED])
            ->orderBy('issued_at');
    }

    public function scopeRequested($query)
    {
        return $query->where('status', self::STATUS_REQUESTED);
    }

    public function scopeUpcoming($query)
    {
        return $query->whereIn('status', [self::STATUS_REQUESTED, self::STATUS_RESERVED, self::STATUS_CLAIMED]);
    }

    protected function isActive(): Attribute
    {
        return Attribute::get(fn () => in_array($this->status, [self::STATUS_RESERVED, self::STATUS_CLAIMED], true));
    }

    protected function isRequest(): Attribute
    {
        return Attribute::get(fn () => $this->source === self::SOURCE_TEACHER_REQUEST);
    }
}
