<?php

declare(strict_types=1);

namespace Simtabi\Laranail\IpIntel\Contracts;

use Simtabi\Laranail\Atlas\Core\Network\IpAddress;
use Simtabi\Laranail\IpIntel\Data\PlaceInfo;

/**
 * A source that can place an address more precisely than its country.
 *
 * **No freely redistributable dataset supports this.** City-level geolocation
 * is inferred from latency, user reports and commercial arrangements, none of
 * which are in a registry file. A driver implementing this is a paid feed, and
 * the interface exists so that fact is visible in the type rather than as a
 * `city` field that is always null.
 */
interface ResolvesCity extends IntelDriver
{
    public function place(IpAddress $address): ?PlaceInfo;
}
