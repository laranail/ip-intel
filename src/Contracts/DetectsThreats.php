<?php

declare(strict_types=1);

namespace Simtabi\Laranail\IpIntel\Contracts;

use Simtabi\Laranail\Atlas\Core\Network\IpAddress;
use Simtabi\Laranail\IpIntel\Data\ThreatSignals;

/**
 * A source that can say something about how an address is being used.
 *
 * Also not derivable from registry data. Whether an address is a VPN exit, a
 * datacentre or a residential proxy is an observation someone made, not a fact
 * anyone published.
 */
interface DetectsThreats extends IntelDriver
{
    public function threats(IpAddress $address): ?ThreatSignals;
}
