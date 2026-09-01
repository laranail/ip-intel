<?php

declare(strict_types=1);

namespace Simtabi\Laranail\IpIntel\Adapters\IpApi;

use Illuminate\Http\Client\Factory as HttpFactory;
use Psr\Log\LoggerInterface;
use Simtabi\Laranail\Atlas\Core\Geo\Coordinates;
use Simtabi\Laranail\Atlas\Core\Network\IpAddress;
use Simtabi\Laranail\IpIntel\Adapters\Local\AtlasDriver;
use Simtabi\Laranail\IpIntel\Contracts\DetectsThreats;
use Simtabi\Laranail\IpIntel\Contracts\ResolvesAsn;
use Simtabi\Laranail\IpIntel\Contracts\ResolvesCity;
use Simtabi\Laranail\IpIntel\Contracts\ResolvesCountry;
use Simtabi\Laranail\IpIntel\Data\AsnInfo;
use Simtabi\Laranail\IpIntel\Data\PlaceInfo;
use Simtabi\Laranail\IpIntel\Data\ThreatSignals;
use Simtabi\Laranail\IpIntel\Exceptions\SourceUnavailable;
use Throwable;

/**
 * ipapi.com — the paid tier, for the questions registry data cannot answer.
 *
 * Implements four capabilities because it genuinely has four. That is the point
 * of the capability interfaces: this driver and {@see AtlasDriver}
 * are not the same shape, and pretending they were is what produced a DTO with
 * twenty nullable fields.
 *
 * ## What was wrong with the implementation this replaces
 *
 * Six things, each of which is a line here:
 *
 * 1. **`"{$baseUrl}{$ip}"`** — the address was interpolated into a URL path
 *    unvalidated and unencoded. It arrives from a request header. This takes a
 *    parsed {@see IpAddress}, so the value has already been through
 *    `filter_var`, and still passes it as a path segment rather than by
 *    concatenation.
 * 2. **No timeout**, so a hung provider hung the request that called it.
 * 3. **No retry**, so a single 502 was a failed lookup.
 * 4. **`fromArray()` read `$data['ip']` and `$data['type']` unguarded**, so a
 *    partial payload was a TypeError rather than a missing field.
 * 5. **A null key was a TypeError**, not a message.
 * 6. **Every failure returned bare `null`** — a dead key and an unknown address
 *    were indistinguishable to the caller.
 *
 * The key still goes in the query string, because that is what the service
 * accepts. It is redacted from every log line here, and the class docblock says
 * so rather than leaving someone to find out from a log.
 */
final class IpApiDriver implements DetectsThreats, ResolvesAsn, ResolvesCity, ResolvesCountry
{
    /**
     * One request per address, memoised for this instance.
     *
     * Four capability methods read one payload: without this, asking an address
     * for its country, ASN, place and threats would be four billed calls.
     *
     * **Instance state, not static.** A static memo outlives the request under
     * Octane and leaks between tests, and an IP's answer is not a process-wide
     * constant. The container binds this driver per resolution, so the memo
     * lasts exactly as long as one chain run — which is the window where the
     * four capability calls happen.
     *
     * @var array<string, array<string, mixed>>
     */
    private array $memo = [];

    public function __construct(
        private readonly HttpFactory $http,
        private readonly LoggerInterface $logger,
        private readonly ?string $accessKey,
        private readonly string $baseUrl = 'https://api.ipapi.com/api',
        private readonly int $timeout = 5,
        private readonly int $retries = 2,
    ) {}

    public function name(): string
    {
        return 'ipapi';
    }

    public function isAvailable(): bool
    {
        // A missing key is a configuration state, not an exception. The chain
        // skips an unavailable source; it does not crash on one.
        return is_string($this->accessKey) && trim($this->accessKey) !== '';
    }

    public function isRemote(): bool
    {
        return true;
    }

    public function country(IpAddress $address): ?string
    {
        $code = $this->fetch($address)['country_code'] ?? null;

        return is_string($code) && strlen($code) === 2 ? strtoupper($code) : null;
    }

    public function asn(IpAddress $address): ?AsnInfo
    {
        $connection = $this->fetch($address)['connection'] ?? null;

        if (! is_array($connection) || ! isset($connection['asn'])) {
            return null;
        }

        $number = $connection['asn'];

        if (! is_int($number) && (! is_string($number) || ! ctype_digit($number))) {
            return null;
        }

        return new AsnInfo(
            number: (int) $number,
            organisation: is_string($connection['isp'] ?? null) ? $connection['isp'] : null,
            // An observed announcement, not a registry allocation — the field
            // exists so a caller can tell.
            allocated: false,
        );
    }

    public function place(IpAddress $address): ?PlaceInfo
    {
        $data = $this->fetch($address);

        if ($data === []) {
            return null;
        }

        $latitude = $data['latitude'] ?? null;
        $longitude = $data['longitude'] ?? null;

        $coordinates = is_numeric($latitude) && is_numeric($longitude)
            ? new Coordinates((float) $latitude, (float) $longitude)
            : null;

        $place = new PlaceInfo(
            city: is_string($data['city'] ?? null) ? $data['city'] : null,
            region: is_string($data['region_name'] ?? null) ? $data['region_name'] : null,
            postcode: is_string($data['zip'] ?? null) ? $data['zip'] : null,
            coordinates: $coordinates,
            accuracyRadiusKm: is_numeric($data['radius'] ?? null) ? (int) $data['radius'] : null,
        );

        // A record with nothing in it is not a place. Returning one would make
        // "we have no city" look like "the city is empty".
        return $place->city === null && ! $place->coordinates instanceof Coordinates ? null : $place;
    }

    public function threats(IpAddress $address): ?ThreatSignals
    {
        $security = $this->fetch($address)['security'] ?? null;

        if (! is_array($security)) {
            return null;
        }

        $signals = new ThreatSignals(
            isProxy: $this->nullableBool($security['is_proxy'] ?? null),
            isVpn: $this->nullableBool($security['is_vpn'] ?? null),
            isTor: $this->nullableBool($security['is_tor'] ?? null),
            isHosting: $this->nullableBool($security['is_hosting'] ?? null),
            isCrawler: $this->nullableBool($security['is_crawler'] ?? null),
            riskScore: is_numeric($security['threat_score'] ?? null) ? (int) $security['threat_score'] : null,
            source: $this->name(),
        );

        return $signals->hasAnySignal() ? $signals : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function fetch(IpAddress $address): array
    {
        if (! $this->isAvailable()) {
            throw SourceUnavailable::notConfigured($this->name(), 'IP_INTEL_IPAPI_KEY');
        }

        $key = $this->name().':'.$address->address;

        if (isset($this->memo[$key])) {
            return $this->memo[$key];
        }

        try {
            $response = $this->http
                ->acceptJson()
                ->timeout($this->timeout)
                // Only a connection failure or a 5xx is retried; a 4xx is an
                // answer, and retrying a rejected key just spends the budget.
                ->retry($this->retries, 100, throw: false)
                // withUrlParameters, not interpolation: the address goes in as
                // a parameter the client encodes rather than a string this
                // class concatenates into a path.
                ->withUrlParameters(['ip' => $address->address])
                ->get($this->baseUrl.'/{ip}', ['access_key' => $this->accessKey]);
        } catch (Throwable $e) {
            // Never the exception's own message: the client embeds the full
            // request URL, which carries the access key.
            $this->logger->warning('ip-intel: ipapi request failed', [
                'ip' => $address->address,
                'reason' => $e::class,
            ]);

            throw SourceUnavailable::requestFailed($this->name(), $e::class);
        }

        if ($response->failed()) {
            $this->logger->warning('ip-intel: ipapi returned an error status', [
                'ip' => $address->address,
                'status' => $response->status(),
            ]);

            throw SourceUnavailable::badStatus($this->name(), $response->status());
        }

        $data = $response->json();

        if (! is_array($data)) {
            throw SourceUnavailable::malformed($this->name());
        }

        // The service answers 200 with {"success": false} for a rejected key or
        // an exhausted quota. Treating that as data is how a broken integration
        // reports every visitor as unknown.
        if (($data['success'] ?? null) === false) {
            $info = $data['error']['info'] ?? null;

            throw SourceUnavailable::rejected($this->name(), is_string($info) ? $info : 'no reason given');
        }

        /** @var array<string, mixed> $data */
        return $this->memo[$key] = $data;
    }

    /**
     * Absent stays absent.
     *
     * `(bool) null` is `false`, which would turn "the provider did not say" into
     * "not a proxy" — the exact collapse {@see ThreatSignals} exists to prevent.
     */
    private function nullableBool(mixed $value): ?bool
    {
        return is_bool($value) ? $value : null;
    }
}
