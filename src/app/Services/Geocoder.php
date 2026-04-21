<?php

namespace App\Services;

interface Geocoder
{
    /**
     * Geocode a single address.
     *
     * @return array{lat: float, lng: float}
     *
     * @throws \InvalidArgumentException when address is empty
     * @throws \RuntimeException         on any failure (network, not found, parse error)
     */
    public function geocode(string $address): array;
}
