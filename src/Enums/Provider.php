<?php

declare(strict_types=1);

namespace Simtabi\Laranail\IpIntel\Enums;

/**
 * The allow-list of sources.
 *
 * The same gate the rest of the family uses: a config value is resolved through
 * `tryFrom()`, so a name that is not a case never reaches a factory and cannot
 * become a class name or a method name. `IpIntel::extend()` takes a closure.
 */
enum Provider: string
{
    /**
     * The reverse proxy already told us. CF-IPCountry and its equivalents are
     * free, edge-supplied and require no lookup — which is why an application
     * behind Cloudflare should almost never pay for a country.
     */
    case EdgeHeader = 'edge';

    /**
     * `laranail/atlas`' offline registry table. Country only, no network.
     */
    case Local = 'local';

    /**
     * ipapi.com. Country, city, ASN and threat signals, metered, remote.
     */
    case IpApi = 'ipapi';

    public function isRemote(): bool
    {
        return $this === self::IpApi;
    }
}
