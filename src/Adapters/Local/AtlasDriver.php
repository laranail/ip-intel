<?php

declare(strict_types=1);

namespace Simtabi\Laranail\IpIntel\Adapters\Local;

use Simtabi\Laranail\Atlas\Core\Network\IpAddress;
use Simtabi\Laranail\IpIntel\Contracts\ResolvesCountry;
use Simtabi\Laranail\Atlas\Core\Contracts\IpCountryResolver;

/**
 * `laranail/atlas`' offline registry table.
 *
 * Country only, and that is the honest limit of the data rather than an
 * unfinished implementation: regional-registry delegation files record which
 * registry allocated which block to which country, and contain no city, no ISP
 * name and no proxy flag. A driver claiming otherwise from this source would be
 * guessing.
 *
 * Implements exactly one capability for that reason. Asking it for an ASN is a
 * type error rather than a null.
 */
final readonly class AtlasDriver implements ResolvesCountry
{
    public function __construct(
        private IpCountryResolver $resolver,
    ) {}

    public function name(): string
    {
        return 'local';
    }

    public function isAvailable(): bool
    {
        return $this->resolver->isReady();
    }

    public function isRemote(): bool
    {
        return false;
    }

    public function country(IpAddress $address): ?string
    {
        return $this->resolver->countryFor($address);
    }
}
