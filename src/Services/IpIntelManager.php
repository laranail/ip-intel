<?php

declare(strict_types=1);

namespace Simtabi\Laranail\IpIntel\Services;

use Closure;
use LogicException;
use Illuminate\Http\Request;
use Simtabi\Laranail\IpIntel\Enums\Provider;
use Illuminate\Contracts\Container\Container;
use Simtabi\Laranail\IpIntel\Data\IpIntelResult;
use Simtabi\Laranail\Atlas\Services\AtlasService;
use Simtabi\Laranail\Atlas\Core\Network\IpAddress;
use Simtabi\Laranail\IpIntel\Contracts\IntelDriver;
use Simtabi\Laranail\IpIntel\Support\IpIntelConfig;
use Simtabi\Laranail\IpIntel\Adapters\IpApi\IpApiDriver;
use Simtabi\Laranail\IpIntel\Adapters\Local\AtlasDriver;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Simtabi\Laranail\IpIntel\Adapters\EdgeHeader\EdgeHeaderDriver;

/**
 * The entry point: builds the chain, caches what is worth caching, and hands
 * out results.
 *
 * `extend()` takes a **closure, not a class name**, and {@see Provider} is the
 * allow-list — the same gate as the rest of the family, for the same reason: a
 * driver name arrives from config, and a config value must never become
 * something the container instantiates by name.
 */
final class IpIntelManager
{
    /** The address an unparseable input falls back to. Reserved, so it answers Reserved. */
    private const string UNSPECIFIED = '0.0.0.0';

    /** @var array<string, Closure(Container): IntelDriver> */
    private array $custom = [];

    public function __construct(
        private readonly Container $container,
        private readonly IpIntelConfig $config,
        private readonly AtlasService $atlas,
        private readonly CacheRepository $cache,
    ) {}

    /**
     * @param Closure(Container): IntelDriver $factory
     */
    public function extend(string $name, Closure $factory): self
    {
        $this->custom[$name] = $factory;

        return $this;
    }

    /**
     * The country for an address — the cheap question.
     */
    public function country(IpAddress|string $address): IpIntelResult
    {
        return $this->lookup($address, full: false);
    }

    /**
     * Everything any configured source can supply.
     */
    public function full(IpAddress|string $address): IpIntelResult
    {
        return $this->lookup($address, full: true);
    }

    /**
     * The current request's client, with the edge header in play.
     *
     * The method most applications want, and the one that costs nothing behind
     * a CDN.
     */
    public function forRequest(?Request $request = null): IpIntelResult
    {
        $request ??= $this->container->make('request');
        $ip = $request instanceof Request ? $request->ip() : null;

        return $this->country($ip ?? self::UNSPECIFIED);
    }

    /**
     * @return list<string>
     */
    public function available(): array
    {
        $builtIn = array_map(static fn (Provider $p): string => $p->value, Provider::cases());

        return array_values(array_unique([...$builtIn, ...array_keys($this->custom)]));
    }

    public function chain(): ResolverChain
    {
        $drivers = [];

        foreach ($this->config->chain() as $name) {
            $driver = $this->driver($name);

            if ($driver instanceof IntelDriver) {
                $drivers[] = $driver;
            }
        }

        return new ResolverChain($drivers, $this->atlas);
    }

    private function lookup(IpAddress|string $address, bool $full): IpIntelResult
    {
        if (! $this->config->bool('enabled', true)) {
            return IpIntelResult::disabled($this->parse($address));
        }

        $parsed = $this->parse($address);

        if (! $this->config->bool('cache.enabled', true)) {
            return $this->resolve($parsed, $full);
        }

        $key = $this->config->string('cache.prefix', 'laranail.ip-intel')
            . ':' . ($full ? 'full' : 'country')
            . ':' . $parsed->address;

        $cached = $this->cache->get($key);

        if ($cached instanceof IpIntelResult) {
            return $cached;
        }

        $result = $this->resolve($parsed, $full);

        // A failure is never cached. Storing a dead key's answer for a day
        // means the outage outlives the fix, and the person who fixes it sees
        // no change.
        if ($result->outcome->isCacheable()) {
            $this->cache->put($key, $result, $this->config->int('cache.ttl', 1440) * 60);
        }

        return $result;
    }

    private function resolve(IpAddress $address, bool $full): IpIntelResult
    {
        return $full ? $this->chain()->full($address) : $this->chain()->country($address);
    }

    /**
     * An unparseable address becomes the unspecified one, which is reserved —
     * so it yields a Reserved outcome rather than throwing at whatever call
     * site passed a request header straight through.
     */
    private function parse(IpAddress|string $address): IpAddress
    {
        if ($address instanceof IpAddress) {
            return $address;
        }

        $parsed = IpAddress::parse($address) ?? IpAddress::parse(self::UNSPECIFIED);

        // 0.0.0.0 parses; if it ever stops, that is a broken atlas rather than
        // bad input, and silently continuing with a wrong address would be
        // worse than saying so.
        return $parsed ?? throw new LogicException(
            'atlas could not parse ' . self::UNSPECIFIED . ', which every IPv4 parser accepts.',
        );
    }

    private function driver(string $name): ?IntelDriver
    {
        if (isset($this->custom[$name])) {
            return ($this->custom[$name])($this->container);
        }

        $provider = Provider::tryFrom($name);

        if ($provider === null) {
            return null;
        }

        return match ($provider) {
            Provider::EdgeHeader => $this->container->make(EdgeHeaderDriver::class),
            Provider::Local      => $this->container->make(AtlasDriver::class),
            Provider::IpApi      => $this->container->make(IpApiDriver::class),
        };
    }
}
