<?php

declare(strict_types=1);

namespace Simtabi\Laranail\IpIntel\Data;

/**
 * What a source observed about how an address is used.
 *
 * Every field is **nullable, not false**, and the distinction is the whole
 * point: `false` means a source looked and found nothing, `null` means nobody
 * looked. A guard that treats an unanswered question as "not a proxy" is a
 * guard that stops working the day its provider key expires, silently.
 */
final readonly class ThreatSignals
{
    public function __construct(
        public ?bool $isProxy = null,
        public ?bool $isVpn = null,
        public ?bool $isTor = null,
        public ?bool $isHosting = null,
        public ?bool $isCrawler = null,
        /** 0–100 where a source supplies one; scales are not comparable across sources. */
        public ?int $riskScore = null,
        public ?string $source = null,
    ) {}

    /**
     * Whether anything was actually observed.
     *
     * What a caller should check before acting on the rest.
     */
    public function hasAnySignal(): bool
    {
        return $this->isProxy !== null
            || $this->isVpn !== null
            || $this->isTor !== null
            || $this->isHosting !== null
            || $this->isCrawler !== null
            || $this->riskScore !== null;
    }

    /**
     * True only where a source affirmatively said so.
     *
     * Deliberately not `?? false` at the call site: an unanswered question is
     * not a clean address.
     */
    public function isAnonymised(): bool
    {
        return $this->isProxy === true || $this->isVpn === true || $this->isTor === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'is_proxy' => $this->isProxy,
            'is_vpn' => $this->isVpn,
            'is_tor' => $this->isTor,
            'is_hosting' => $this->isHosting,
            'is_crawler' => $this->isCrawler,
            'risk_score' => $this->riskScore,
            'source' => $this->source,
        ];
    }
}
