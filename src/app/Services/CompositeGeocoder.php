<?php

namespace App\Services;

/**
 * Try each provider in order; return the first that succeeds. If all fail,
 * throw a combined RuntimeException with each provider's error so operators
 * can see whether the issue is address quality (all providers report
 * "not found") or network / rate limiting (mixed messages).
 */
class CompositeGeocoder implements Geocoder
{
    /** @var array<int, Geocoder> */
    private array $providers;

    public function __construct(Geocoder ...$providers)
    {
        $this->providers = $providers;
    }

    public function geocode(string $address): array
    {
        $errors = [];
        foreach ($this->providers as $provider) {
            try {
                return $provider->geocode($address);
            } catch (\InvalidArgumentException $e) {
                // Bad input — don't try the next provider.
                throw $e;
            } catch (\Throwable $e) {
                $errors[] = class_basename($provider) . ': ' . $e->getMessage();
            }
        }

        throw new \RuntimeException('All geocoders failed — ' . implode(' | ', $errors));
    }
}
