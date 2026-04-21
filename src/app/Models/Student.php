<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Student extends Model
{
    use LogsActivity;
    use SoftDeletes;

    public const ELIGIBILITY_THRESHOLD_MILES = 2.5;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'home_lat' => 'float',
            'home_lng' => 'float',
            'distance_to_school_miles' => 'float',
            'hazardous_route' => 'boolean',
            'active' => 'boolean',
            'geocoded_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logUnguarded()
            ->logExcept(['updated_at'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('student');
    }

    protected function fullName(): Attribute
    {
        return Attribute::get(fn () => trim("{$this->first_name} {$this->last_name}"));
    }

    protected function isEligibleRider(): Attribute
    {
        return Attribute::get(function () {
            if ($this->hazardous_route) {
                return true;
            }
            return $this->distance_to_school_miles !== null
                && $this->distance_to_school_miles >= self::ELIGIBILITY_THRESHOLD_MILES;
        });
    }

    protected function isGeocoded(): Attribute
    {
        return Attribute::get(fn () => $this->home_lat !== null && $this->home_lng !== null);
    }

    public static function grades(): array
    {
        return [
            'PK' => 'Pre-K',
            'K' => 'Kindergarten',
            '1' => '1st',
            '2' => '2nd',
            '3' => '3rd',
            '4' => '4th',
            '5' => '5th',
            '6' => '6th',
            '7' => '7th',
            '8' => '8th',
            '9' => '9th',
            '10' => '10th',
            '11' => '11th',
            '12' => '12th',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeMissingGeocode($query)
    {
        return $query->whereNotNull('home_address')
            ->where(function ($q) {
                $q->whereNull('home_lat')->orWhereNull('home_lng');
            });
    }
}
