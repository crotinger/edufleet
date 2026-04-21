<?php

namespace App\Jobs;

use App\Models\Student;
use App\Services\NominatimGeocoder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable as FoundationQueueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GeocodeStudent implements ShouldQueue
{
    use Queueable;
    use FoundationQueueable;

    public int $tries = 1;

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

        try {
            $result = $geocoder->geocode($student->home_address);

            $student->forceFill([
                'home_lat' => $result['lat'],
                'home_lng' => $result['lng'],
                'geocoded_at' => now(),
                'last_geocode_attempted_at' => now(),
                'last_geocode_error' => null,
            ])->save();
        } catch (\Throwable $e) {
            $student->forceFill([
                'last_geocode_attempted_at' => now(),
                'last_geocode_error' => Str::limit($e->getMessage(), 500),
            ])->save();

            Log::warning('Student geocode failed', [
                'student_id' => $student->id,
                'address' => $student->home_address,
                'error' => $e->getMessage(),
            ]);
        } finally {
            // Honor Nominatim's 1 req/sec guideline whether the call succeeded
            // or not — back-to-back 400s are still requests.
            sleep(1);
        }
    }
}
