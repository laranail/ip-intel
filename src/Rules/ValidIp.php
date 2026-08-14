<?php

declare(strict_types=1);

namespace Simtabi\Laranail\IpIntel\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Simtabi\Laranail\Atlas\Core\Network\IpAddress;

/**
 * A parseable IP address.
 *
 * Reuses atlas' parser rather than Laravel's `ip` rule, and the difference is
 * deliberate: this is the same code that decides whether an address is
 * reserved, so a value that validates here is one the resolver can classify.
 * Two parsers means two opinions about `01.02.03.04`, which some resolvers read
 * as octal.
 *
 * Exported rather than kept private, so a consumer validating an address in
 * their own request uses the same rule this package's API does.
 */
final readonly class ValidIp implements ValidationRule
{
    public function __construct(
        /** Refuse private, loopback and other non-routable addresses. */
        private bool $publicOnly = false,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('The :attribute must be an IP address.');

            return;
        }

        $address = IpAddress::parse($value);

        if (! $address instanceof IpAddress) {
            $fail('The :attribute must be a valid IPv4 or IPv6 address.');

            return;
        }

        if ($this->publicOnly && $address->isReserved()) {
            $fail('The :attribute must be a publicly routable address; private and loopback ranges cannot be located.');
        }
    }
}
