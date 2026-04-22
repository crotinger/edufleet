<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TripStudentBoarding extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'boarded' => 'boolean',
            'boarded_at' => 'datetime',
        ];
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    protected static function booted(): void
    {
        // Keep the parent Trip's denormalized rider counts in sync whenever
        // any boarding changes. Same-transaction so Filament inline toggles /
        // bulk actions / CreateAction all reflect immediately.
        $sync = function (TripStudentBoarding $boarding) {
            $boarding->loadMissing('trip');
            $boarding->trip?->syncRidersFromBoardings();
        };
        static::saved($sync);
        static::deleted($sync);
    }
}
