<?php

namespace App\Models;

use App\Models\Concerns\HasAttachments;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

class Driver extends Model
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
            ->useLogName('driver');
    }

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_ON_LEAVE = 'on_leave';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'hired_on' => 'date',
            'terminated_on' => 'date',
            'license_issued_on' => 'date',
            'license_expires_on' => 'date',
            'dot_medical_expires_on' => 'date',
            'first_aid_cpr_expires_on' => 'date',
            'defensive_driving_expires_on' => 'date',
            'endorsements' => 'array',
        ];
    }

    protected function fullName(): Attribute
    {
        return Attribute::get(fn () => trim("{$this->first_name} {$this->last_name}"));
    }

    public static function statuses(): array
    {
        return [
            self::STATUS_ACTIVE => 'Active',
            self::STATUS_INACTIVE => 'Inactive',
            self::STATUS_ON_LEAVE => 'On leave',
        ];
    }

    public static function licenseClasses(): array
    {
        return [
            'A' => 'Class A (combination >26,000 lb, towed >10,000 lb)',
            'B' => 'Class B (single vehicle >26,000 lb — most school buses)',
            'C' => 'Class C (standard non-commercial, or CDL C with endorsement)',
        ];
    }

    public static function endorsementOptions(): array
    {
        return [
            'P' => 'P — Passenger',
            'S' => 'S — School Bus',
            'T' => 'T — Double/Triple Trailers',
            'N' => 'N — Tank Vehicles',
            'H' => 'H — Hazardous Materials',
            'X' => 'X — Tank + Hazmat',
        ];
    }

    public function trips(): HasMany
    {
        return $this->hasMany(Trip::class);
    }

    public function drugAlcoholTests(): HasMany
    {
        return $this->hasMany(DrugAlcoholTest::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
