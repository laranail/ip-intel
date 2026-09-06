<?php

declare(strict_types=1);

namespace Simtabi\Laranail\IpIntel\Exceptions;

use RuntimeException;
use Simtabi\Laranail\IpIntel\Enums\Outcome;

/**
 * A source was asked and could not answer — as distinct from having no answer.
 *
 * The chain catches this, records it, and carries on to the next source. It
 * surfaces as {@see Outcome::Unavailable} rather
 * than as a null, because a dead key is an incident and an unknown address is
 * Tuesday.
 */
final class SourceUnavailable extends RuntimeException
{
    public static function notConfigured(string $driver, string $envVar): self
    {
        return new self(sprintf(
            'The [%s] source has no credential. Set %s, or drop it from laranail.ip-intel.chain.',
            $driver,
            $envVar,
        ));
    }

    public static function requestFailed(string $driver, string $reason): self
    {
        return new self(sprintf('The [%s] source could not be reached (%s).', $driver, $reason));
    }

    public static function badStatus(string $driver, int $status): self
    {
        return new self(sprintf('The [%s] source answered with HTTP %d.', $driver, $status));
    }

    public static function rejected(string $driver, string $why): self
    {
        return new self(sprintf(
            'The [%s] source rejected the request: %s. This is usually a dead key or an exhausted quota — '
            . 'note that it answers 200 with success:false, so it is not an HTTP error.',
            $driver,
            $why,
        ));
    }

    public static function malformed(string $driver): self
    {
        return new self(sprintf('The [%s] source returned a body that is not JSON.', $driver));
    }
}
