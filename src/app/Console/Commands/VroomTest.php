<?php

namespace App\Console\Commands;

use App\Services\VroomClient;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('vroom:test')]
#[Description('Post a small 3-stop tour to the configured VROOM server and print the result. Use to verify the self-hosted OSRM + VROOM stack is reachable from the app container.')]
class VroomTest extends Command
{
    public function handle(VroomClient $vroom): int
    {
        if (! $vroom->isConfigured()) {
            $this->error('VROOM_URL is not set. Set it in .env and make sure the vrp compose profile is up:');
            $this->line('  docker compose --profile vrp up -d');
            return self::FAILURE;
        }

        // Four Wichita-area points: depot → two pickups → school
        $depot = [37.6922, -97.3375];
        $school = [37.7200, -97.3100];
        $stops = [
            [37.7420, -97.4300],
            [37.6700, -97.3000],
        ];

        $this->line('Posting a 2-pickup tour to VROOM...');

        $start = microtime(true);
        try {
            $result = $vroom->solveSingleTour($stops, $depot, $school);
        } catch (\Throwable $e) {
            $this->newLine();
            $this->error('FAILED: ' . $e->getMessage());
            return self::FAILURE;
        }
        $ms = (int) round((microtime(true) - $start) * 1000);

        $this->newLine();
        $this->info("OK ({$ms} ms)");
        $this->line('  visit order: ' . json_encode($result['order']));
        $this->line('  distance:    ' . round($result['distance_meters'] / 1609.344, 2) . ' mi');
        $this->line('  duration:    ' . (int) round($result['duration_seconds'] / 60) . ' min');
        $this->line('  geometry:    ' . (is_string($result['geometry']) ? (strlen($result['geometry']) . ' chars') : 'n/a'));
        return self::SUCCESS;
    }
}
