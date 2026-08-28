<?php

declare(strict_types=1);

namespace Simtabi\Laranail\IpIntel\Data;

use Simtabi\Laranail\Atlas\Core\Geo\Coordinates;

/**
 * A sub-country location, from a source that can supply one.
 *
 * `$accuracyRadiusKm` is not decoration. City-level geolocation is an inference,
 * and a provider reporting "London, radius 200 km" is saying something closer to
 * "southern England". A caller drawing a pin on a map without reading it is
 * showing a precision the data does not have.
 */
final readonly class PlaceInfo
{
    public function __construct(
        public ?string $city = null,
        public ?string $region = null,
        public ?string $postcode = null,
        public ?Coordinates $coordinates = null,
        public ?int $accuracyRadiusKm = null,
    ) {}

    /**
     * Whether this is precise enough to act on.
     *
     * The threshold is the caller's, and the default of 50 km is chosen to
     * exclude the country-centroid answers some providers return when they have
     * nothing better.
     */
    public function isPrecise(int $withinKm = 50): bool
    {
        return $this->accuracyRadiusKm !== null && $this->accuracyRadiusKm <= $withinKm;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'city'               => $this->city,
            'region'             => $this->region,
            'postcode'           => $this->postcode,
            'coordinates'        => $this->coordinates?->toArray(),
            'accuracy_radius_km' => $this->accuracyRadiusKm,
        ];
    }
}
