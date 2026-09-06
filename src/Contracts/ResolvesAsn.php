<?php

declare(strict_types=1);

namespace Simtabi\Laranail\IpIntel\Contracts;

use Simtabi\Laranail\IpIntel\Data\AsnInfo;
use Simtabi\Laranail\Atlas\Core\Network\IpAddress;

/**
 * A source that can name the network an address is announced from.
 *
 * Registry delegation data carries allocation; a routing-table feed carries
 * what is actually announced. They disagree more often than is comfortable, so
 * {@see AsnInfo} records which kind it is.
 */
interface ResolvesAsn extends IntelDriver
{
    public function asn(IpAddress $address): ?AsnInfo;
}
