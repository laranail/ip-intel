<?php

declare(strict_types=1);

namespace Simtabi\Laranail\IpIntel\Enums;

/**
 * Why a lookup ended the way it did.
 *
 * Five cases where the implementation this replaces had one `null`. They need
 * five different responses: a reserved address is normal, a registry gap is
 * normal, a dead API key is an incident, and a disabled feature is a
 * configuration choice someone made on purpose.
 */
enum Outcome: string
{
    case Found = 'found';

    /** Private, loopback, link-local, multicast, documentation — not routable. */
    case Reserved = 'reserved';

    /** Every source was asked; none had this address. */
    case NotFound = 'not_found';

    /** A source failed: dead key, timeout, error response. Operational. */
    case Unavailable = 'unavailable';

    /** Nothing was asked; the feature is off. */
    case Disabled = 'disabled';

    /**
     * Whether this outcome should be cached.
     *
     * A failure must not be: caching a dead key's answer for a day means the
     * outage outlives the fix. A reserved address is cacheable forever — that
     * fact cannot change.
     */
    public function isCacheable(): bool
    {
        return match ($this) {
            self::Found, self::Reserved, self::NotFound => true,
            self::Unavailable, self::Disabled           => false,
        };
    }
}
