<?php

declare(strict_types=1);

namespace Simtabi\Laranail\IpIntel\Data;

use JsonSerializable;
use Simtabi\Laranail\IpIntel\Enums\Outcome;
use Simtabi\Laranail\Atlas\Core\Network\IpAddress;
use Simtabi\Laranail\Atlas\Core\Country\CountryRecord;

/**
 * What the chain found, and how.
 *
 * ## Why this is not a nullable DTO
 *
 * The implementation this replaces returned `?GeoIpData` — bare `null` for
 * every failure. A caller could not distinguish:
 *
 *   - the address is reserved and belongs to no country
 *   - the registries have a gap there
 *   - the API key is dead
 *   - the provider timed out
 *   - the lookup was never attempted because the feature is off
 *
 * Those need five different responses, and collapsing them into `null` means an
 * application either treats a dead key as an unknown visitor forever, or alerts
 * on every unroutable address. {@see Outcome} names which one happened.
 *
 * `$sources` records which drivers contributed, because with a chain the answer
 * comes from somewhere and "somewhere" is the difference between an edge header
 * and a paid API call.
 */
final readonly class IpIntelResult implements JsonSerializable
{
    /**
     * @param list<string> $sources drivers that contributed, in order
     */
    private function __construct(
        public IpAddress $address,
        public Outcome $outcome,
        public ?string $countryCode = null,
        public ?CountryRecord $country = null,
        public ?AsnInfo $asn = null,
        public ?PlaceInfo $place = null,
        public ?ThreatSignals $threats = null,
        public array $sources = [],
        public ?string $message = null,
        public bool $madeNetworkCall = false,
    ) {}

    /**
     * @param list<string> $sources
     */
    public static function found(
        IpAddress $address,
        ?string $countryCode = null,
        ?CountryRecord $country = null,
        ?AsnInfo $asn = null,
        ?PlaceInfo $place = null,
        ?ThreatSignals $threats = null,
        array $sources = [],
        bool $madeNetworkCall = false,
    ): self {
        return new self(
            address: $address,
            outcome: Outcome::Found,
            countryCode: $countryCode,
            country: $country,
            asn: $asn,
            place: $place,
            threats: $threats,
            sources: $sources,
            madeNetworkCall: $madeNetworkCall,
        );
    }

    /**
     * The address is not on the public internet, so no source can place it.
     *
     * Distinct from a gap: 10.0.0.1 is in use on millions of networks in every
     * country there is, and answering with one of them would be worse than
     * answering nothing.
     */
    public static function reserved(IpAddress $address): self
    {
        return new self(
            address: $address,
            outcome: Outcome::Reserved,
            message: 'The address is private, loopback or otherwise not globally routable.',
        );
    }

    /**
     * Every source was asked and none had an answer.
     *
     * @param list<string> $sources
     */
    public static function notFound(IpAddress $address, array $sources = [], bool $madeNetworkCall = false): self
    {
        return new self(
            address: $address,
            outcome: Outcome::NotFound,
            sources: $sources,
            message: 'No configured source could place this address.',
            madeNetworkCall: $madeNetworkCall,
        );
    }

    /**
     * A source was asked and failed — a dead key, a timeout, a 500.
     *
     * The case that most needs separating from the rest: it is an operational
     * problem, and treating it as "unknown visitor" is how a broken integration
     * runs for months.
     *
     * @param list<string> $sources
     */
    public static function unavailable(IpAddress $address, string $why, array $sources = []): self
    {
        return new self(
            address: $address,
            outcome: Outcome::Unavailable,
            sources: $sources,
            message: $why,
            madeNetworkCall: true,
        );
    }

    /**
     * Nothing was asked, because the feature is switched off.
     */
    public static function disabled(IpAddress $address): self
    {
        return new self(
            address: $address,
            outcome: Outcome::Disabled,
            message: 'IP intelligence is disabled; set laranail.ip-intel.enabled to true.',
        );
    }

    public function isFound(): bool
    {
        return $this->outcome === Outcome::Found;
    }

    /**
     * Whether the caller should treat this as an operational failure.
     *
     * True only for {@see Outcome::Unavailable}. A reserved address and a
     * registry gap are how the internet is, not something to alert on.
     */
    public function needsAttention(): bool
    {
        return $this->outcome === Outcome::Unavailable;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'ip'           => $this->address->address,
            'outcome'      => $this->outcome->value,
            'country_code' => $this->countryCode,
            'country'      => $this->country?->name,
            'flag'         => $this->country?->flag(),
            'continent'    => $this->country?->continent,
            'currencies'   => $this->country?->currencies,
            'asn'          => $this->asn?->toArray(),
            'place'        => $this->place?->toArray(),
            'threats'      => $this->threats?->toArray(),
            'sources'      => $this->sources,
            'network_call' => $this->madeNetworkCall,
            'message'      => $this->message,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
