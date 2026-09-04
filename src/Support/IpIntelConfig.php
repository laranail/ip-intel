<?php

declare(strict_types=1);

namespace Simtabi\Laranail\IpIntel\Support;

use Illuminate\Contracts\Config\Repository as ConfigRepository;

/**
 * Typed reads of this package's config, under one prefix.
 */
final readonly class IpIntelConfig
{
    public const string PREFIX = 'laranail.ip-intel';

    public function __construct(
        private ConfigRepository $config,
    ) {}

    public function string(string $key, string $default = ''): string
    {
        $value = $this->raw($key);

        return is_string($value) && $value !== '' ? $value : $default;
    }

    public function nullableString(string $key): ?string
    {
        $value = $this->raw($key);

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function int(string $key, int $default = 0): int
    {
        $value = $this->raw($key);

        return is_numeric($value) ? (int) $value : $default;
    }

    public function bool(string $key, bool $default = false): bool
    {
        $value = $this->raw($key);

        return is_bool($value) ? $value : $default;
    }

    /**
     * @param array<array-key, mixed> $default
     *
     * @return array<array-key, mixed>
     */
    public function array(string $key, array $default = []): array
    {
        $value = $this->raw($key);

        return is_array($value) ? $value : $default;
    }

    /**
     * The source order. This IS the cost policy: put `edge` first and an
     * application behind a CDN never pays for a country.
     *
     * @return list<string>
     */
    public function chain(): array
    {
        $chain = $this->array('chain', ['edge', 'local']);

        return array_values(array_filter($chain, is_string(...)));
    }

    private function raw(string $key): mixed
    {
        return $this->config->get(self::PREFIX . '.' . $key);
    }
}
