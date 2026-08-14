<?php

declare(strict_types=1);

namespace Simtabi\Laranail\IpIntel\Contracts;

use Simtabi\Laranail\Atlas\Core\Network\IpAddress;

/**
 * A source that can name the country an address belongs to.
 *
 * The one capability nearly every source has, and the only one freely
 * redistributable registry data supports.
 */
interface ResolvesCountry extends IntelDriver
{
    /**
     * ISO 3166-1 alpha-2, or null.
     *
     * Null means this source has no answer — a reserved address, a registry
     * gap, an edge header this request did not carry. It never means "no such
     * country".
     */
    public function country(IpAddress $address): ?string;
}
