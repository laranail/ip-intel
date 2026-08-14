<?php

declare(strict_types=1);

namespace Simtabi\Laranail\IpIntel\Adapters\EdgeHeader;

use Illuminate\Http\Request;
use Simtabi\Laranail\Atlas\Core\Network\IpAddress;
use Simtabi\Laranail\IpIntel\Contracts\ResolvesCountry;

/**
 * The country the reverse proxy already worked out.
 *
 * ## Why this is first in the chain
 *
 * Cloudflare, Vercel, Fastly and AWS CloudFront all resolve the country at the
 * edge and put it in a request header. It is free, it is already computed, and
 * it arrives before any code runs. An application behind one of them that pays
 * a metered API for the same answer is paying for a lookup it was handed.
 *
 * That is not hypothetical: this package exists partly because an application
 * in this family read `CF-IPCountry` directly and never wired up its GeoIP
 * package at all — which was the right instinct and the wrong shape, because
 * the fallback when the header was absent was a hardcoded country.
 *
 * ## It only answers about *this* request
 *
 * A header describes the client that sent it. Asked about any other address it
 * returns null rather than the current visitor's country, which would be a
 * quietly wrong answer rather than a missing one.
 */
final readonly class EdgeHeaderDriver implements ResolvesCountry
{
    /**
     * @param list<string> $headers in priority order
     */
    public function __construct(
        private ?Request $request,
        private array $headers = [
            'CF-IPCountry',            // Cloudflare
            'CloudFront-Viewer-Country', // AWS CloudFront
            'X-Vercel-IP-Country',     // Vercel
            'Fastly-Geo-Country',      // Fastly
            'X-Geo-Country',           // a common convention for a self-managed proxy
        ],
    ) {}

    public function name(): string
    {
        return 'edge';
    }

    public function isAvailable(): bool
    {
        return $this->request instanceof Request;
    }

    public function isRemote(): bool
    {
        return false;
    }

    public function country(IpAddress $address): ?string
    {
        if (! $this->request instanceof Request) {
            return null;
        }

        // The header describes whoever sent this request. Answering with it for
        // a different address would be confidently wrong.
        if (! $this->describesRequest($address)) {
            return null;
        }

        foreach ($this->headers as $header) {
            $value = $this->request->header($header);

            if (! is_string($value)) {
                continue;
            }

            $code = strtoupper(trim($value));

            // Cloudflare sends XX for an address it cannot place, and T1 for
            // Tor. Neither is a country, and both would pass a naive
            // two-letter check.
            if ($code === 'XX' || $code === 'T1' || strlen($code) !== 2 || ! ctype_alpha($code)) {
                continue;
            }

            return $code;
        }

        return null;
    }

    private function describesRequest(IpAddress $address): bool
    {
        $clientIp = $this->request?->ip();

        return is_string($clientIp) && $clientIp === $address->address;
    }
}
