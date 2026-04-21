<?php

namespace App\Console\Commands;

use App\Services\Geocoder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('geocoder:test {address : Address to geocode}')]
#[Description('Hit Nominatim with a single address and print the result or error. Use to verify the geocoder can reach the internet.')]
class GeocoderTest extends Command
{
    public function handle(Geocoder $geocoder): int
    {
        $address = (string) $this->argument('address');

        $this->line("Geocoding: <comment>{$address}</comment>");

        $start = microtime(true);
        try {
            $result = $geocoder->geocode($address);
        } catch (\Throwable $e) {
            $this->newLine();
            $this->error('FAILED: ' . $e->getMessage());
            return self::FAILURE;
        }
        $ms = (int) round((microtime(true) - $start) * 1000);

        $this->newLine();
        $this->info("OK ({$ms} ms)");
        $this->line("  lat: {$result['lat']}");
        $this->line("  lng: {$result['lng']}");
        return self::SUCCESS;
    }
}
