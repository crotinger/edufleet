<?php

namespace App\Jobs;

use App\Models\Student;
use App\Services\NominatimGeocoder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable as FoundationQueueable;

class GeocodeStudent implements ShouldQueue
{
    use Queueable;
    use FoundationQueueable;

    public int $tries = 2;

    public int $timeout = 30;

    public function __construct(
        public int $studentId,
        public bool $force = false,
    ) {}

    public function handle(NominatimGeocoder $geocoder): void
    {
        $student = Student::withTrashed()->find($this->studentId);
        if (! $student || ! filled($student->home_address)) {
            return;
        }

        if (! $this->force && $student->home_lat !== null && $student->home_lng !== null) {
            return;
        }

        $result = $geocoder->geocode($student->home_address);
        if ($result === null) {
            return;
        }

        $student->forceFill([
            'home_lat' => $result['lat'],
            'home_lng' => $result['lng'],
            'geocoded_at' => now(),
        ])->save();

        // Respect Nominatim's 1 req/sec guideline when many jobs queue up.
        sleep(1);
    }
}
