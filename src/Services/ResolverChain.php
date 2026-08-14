<?php

declare(strict_types=1);

namespace Simtabi\Laranail\IpIntel\Services;

use Simtabi\Laranail\Atlas\Core\Network\IpAddress;
use Simtabi\Laranail\Atlas\Services\AtlasService;
use Simtabi\Laranail\IpIntel\Contracts\DetectsThreats;
use Simtabi\Laranail\IpIntel\Contracts\IntelDriver;
use Simtabi\Laranail\IpIntel\Contracts\ResolvesAsn;
use Simtabi\Laranail\IpIntel\Contracts\ResolvesCity;
use Simtabi\Laranail\IpIntel\Contracts\ResolvesCountry;
use Simtabi\Laranail\IpIntel\Data\AsnInfo;
use Simtabi\Laranail\IpIntel\Data\IpIntelResult;
use Simtabi\Laranail\IpIntel\Data\PlaceInfo;
use Simtabi\Laranail\IpIntel\Data\ThreatSignals;
use Simtabi\Laranail\IpIntel\Exceptions\SourceUnavailable;

/**
 * Asks each source in turn, stopping as soon as the question is answered.
 *
 * ## Why a chain rather than a driver
 *
 * Because the cheap answer is usually already available. An application behind
 * Cloudflare is handed the country in a request header before any code runs;
 * asking a metered API for it is paying for something it was given. So the
 * order is:
 *
 * ```
 * edge header  →  no lookup at all, free, already computed
 * local table  →  offline, registry data, country only
 * remote       →  metered, and the only source for city/ASN/threats
 * ```
 *
 * A single configurable "driver" cannot express that, because the right source
 * depends on the *question*: the header can answer country and nothing else, so
 * a chain that treated it as the driver would make ASN unavailable.
 *
 * ## Capability, not configuration
 *
 * Each step asks `$driver instanceof ResolvesAsn` rather than consulting a list
 * of what the driver claims. A source that cannot answer is skipped without
 * being called, which is what makes the promise below true rather than
 * approximate.
 *
 * **A country-only question, with an edge header present, makes zero network
 * calls** — `$result->madeNetworkCall` records it and a test asserts it.
 */
final readonly class ResolverChain
{
    /**
     * @param list<IntelDriver> $drivers in priority order
     */
    public function __construct(
        private array $drivers,
        private AtlasService $atlas,
    ) {}

    /**
     * Country only — the cheap path, and the one most callers want.
     */
    public function country(IpAddress $address): IpIntelResult
    {
        return $this->resolve($address, withAsn: false, withPlace: false, withThreats: false);
    }

    /**
     * Everything any configured source can supply.
     */
    public function full(IpAddress $address): IpIntelResult
    {
        return $this->resolve($address, withAsn: true, withPlace: true, withThreats: true);
    }

    public function resolve(
        IpAddress $address,
        bool $withAsn = false,
        bool $withPlace = false,
        bool $withThreats = false,
    ): IpIntelResult {
        // Asked and answered before any source is consulted. 10.0.0.1 is in use
        // on millions of networks in every country there is, so a lookup would
        // spend a request to return something misleading.
        if ($address->isReserved()) {
            return IpIntelResult::reserved($address);
        }

        $used = [];
        $networkCall = false;
        $failure = null;

        $countryCode = null;
        $asn = null;
        $place = null;
        $threats = null;

        foreach ($this->drivers as $driver) {
            if (! $driver->isAvailable()) {
                continue;
            }

            // Nothing left to ask for. Stopping here is what keeps a remote
            // source out of a country-only lookup.
            if ($this->satisfied($countryCode, $asn, $place, $threats, $withAsn, $withPlace, $withThreats)) {
                break;
            }

            if (! $this->canContribute($driver, $countryCode, $asn, $place, $threats, $withAsn, $withPlace, $withThreats)) {
                continue;
            }

            try {
                $contributed = false;

                if ($countryCode === null && $driver instanceof ResolvesCountry) {
                    $countryCode = $driver->country($address);
                    $contributed = $countryCode !== null;
                }

                if ($withAsn && ! $asn instanceof AsnInfo && $driver instanceof ResolvesAsn) {
                    $asn = $driver->asn($address);
                    $contributed = $contributed || $asn instanceof AsnInfo;
                }

                if ($withPlace && ! $place instanceof PlaceInfo && $driver instanceof ResolvesCity) {
                    $place = $driver->place($address);
                    $contributed = $contributed || $place instanceof PlaceInfo;
                }

                if ($withThreats && ! $threats instanceof ThreatSignals && $driver instanceof DetectsThreats) {
                    $threats = $driver->threats($address);
                    $contributed = $contributed || $threats instanceof ThreatSignals;
                }

                $networkCall = $networkCall || $driver->isRemote();

                if ($contributed) {
                    $used[] = $driver->name();
                }
            } catch (SourceUnavailable $e) {
                // Recorded, not swallowed and not fatal: a later source may
                // still answer, and if none does the caller needs to know a
                // source was broken rather than that the address was unknown.
                $networkCall = $networkCall || $driver->isRemote();
                $failure ??= $e->getMessage();
            }
        }

        if ($countryCode === null && ! $asn instanceof AsnInfo && ! $place instanceof PlaceInfo && ! $threats instanceof ThreatSignals) {
            return $failure === null
                ? IpIntelResult::notFound($address, $used, $networkCall)
                : IpIntelResult::unavailable($address, $failure, $used);
        }

        return IpIntelResult::found(
            address: $address,
            countryCode: $countryCode,
            // Enriched from atlas rather than from the provider: the name, flag,
            // currencies and continent are catalogue facts, and paying an API
            // for fields we already hold offline would be absurd.
            country: $countryCode === null ? null : $this->atlas->country($countryCode),
            asn: $asn,
            place: $place,
            threats: $threats,
            sources: $used,
            madeNetworkCall: $networkCall,
        );
    }

    private function satisfied(
        ?string $countryCode,
        ?AsnInfo $asn,
        ?PlaceInfo $place,
        ?ThreatSignals $threats,
        bool $withAsn,
        bool $withPlace,
        bool $withThreats,
    ): bool {
        return $countryCode !== null
            && (! $withAsn || $asn instanceof AsnInfo)
            && (! $withPlace || $place instanceof PlaceInfo)
            && (! $withThreats || $threats instanceof ThreatSignals);
    }

    /**
     * Whether this driver can supply something still missing.
     *
     * The check that stops a remote source being called for a country an edge
     * header already gave us.
     */
    private function canContribute(
        IntelDriver $driver,
        ?string $countryCode,
        ?AsnInfo $asn,
        ?PlaceInfo $place,
        ?ThreatSignals $threats,
        bool $withAsn,
        bool $withPlace,
        bool $withThreats,
    ): bool {
        if ($countryCode === null && $driver instanceof ResolvesCountry) {
            return true;
        }

        if ($withAsn && ! $asn instanceof AsnInfo && $driver instanceof ResolvesAsn) {
            return true;
        }

        if ($withPlace && ! $place instanceof PlaceInfo && $driver instanceof ResolvesCity) {
            return true;
        }

        return $withThreats && ! $threats instanceof ThreatSignals && $driver instanceof DetectsThreats;
    }
}
