<?php

declare(strict_types=1);

namespace Simtabi\Laranail\IpIntel\Data;

/**
 * The autonomous system an address belongs to.
 *
 * `$allocated` records whether this came from a registry's allocation record or
 * from an observed routing announcement. They disagree more often than is
 * comfortable — an allocation can sit unannounced for years, and an announcement
 * can come from a network the registry never assigned it to — so a caller
 * deciding "is this a datacentre" should know which it is looking at.
 */
final readonly class AsnInfo
{
    public function __construct(
        public int $number,
        public ?string $organisation = null,
        public ?string $prefix = null,
        /** True for a registry allocation, false for an observed announcement. */
        public bool $allocated = true,
    ) {}

    public function label(): string
    {
        return 'AS'.$this->number.($this->organisation === null ? '' : ' '.$this->organisation);
    }

    /**
     * @return array{number: int, organisation: ?string, prefix: ?string, allocated: bool}
     */
    public function toArray(): array
    {
        return [
            'number' => $this->number,
            'organisation' => $this->organisation,
            'prefix' => $this->prefix,
            'allocated' => $this->allocated,
        ];
    }
}
