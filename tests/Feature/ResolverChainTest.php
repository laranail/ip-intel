<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Simtabi\Laranail\IpIntel\Data\AsnInfo;
use Simtabi\Laranail\IpIntel\Enums\Outcome;
use Simtabi\Laranail\IpIntel\Facades\IpIntel;
use Simtabi\Laranail\IpIntel\Data\ThreatSignals;
use Simtabi\Laranail\Atlas\Core\Network\IpAddress;
use Simtabi\Laranail\IpIntel\Contracts\ResolvesAsn;
use Simtabi\Laranail\IpIntel\Services\IpIntelManager;
use Simtabi\Laranail\IpIntel\Contracts\DetectsThreats;
use Simtabi\Laranail\IpIntel\Contracts\ResolvesCountry;
use Simtabi\Laranail\IpIntel\Exceptions\SourceUnavailable;

/**
 * A country-only source that never leaves the machine.
 */
function offlineCountry(string $name, ?string $answer): ResolvesCountry
{
    return new class($name, $answer) implements ResolvesCountry
    {
        public int $calls = 0;

        public function __construct(private readonly string $label, private readonly ?string $answer) {}

        public function name(): string
        {
            return $this->label;
        }

        public function isAvailable(): bool
        {
            return true;
        }

        public function isRemote(): bool
        {
            return false;
        }

        public function country(IpAddress $address): ?string
        {
            $this->calls++;

            return $this->answer;
        }
    };
}

/**
 * A remote source with every capability — the paid tier.
 */
function remoteEverything(string $name = 'remote'): object
{
    return new class($name) implements DetectsThreats, ResolvesAsn, ResolvesCountry
    {
        public int $calls = 0;

        public function __construct(private readonly string $label) {}

        public function name(): string
        {
            return $this->label;
        }

        public function isAvailable(): bool
        {
            return true;
        }

        public function isRemote(): bool
        {
            return true;
        }

        public function country(IpAddress $address): string
        {
            $this->calls++;

            return 'JP';
        }

        public function asn(IpAddress $address): AsnInfo
        {
            $this->calls++;

            return new AsnInfo(number: 64500, organisation: 'Example Networks', allocated: false);
        }

        public function threats(IpAddress $address): ThreatSignals
        {
            $this->calls++;

            return new ThreatSignals(isVpn: true, source: $this->label);
        }
    };
}

beforeEach(function (): void {
    config()->set('laranail.ip-intel.cache.enabled', false);
});

// -----------------------------------------------------------------------
// The promise: a country question costs nothing when the edge already answered
// -----------------------------------------------------------------------

it('makes no network call for a country when an earlier source answers', function (): void {
    // The whole reason this is a chain rather than a driver. An application
    // behind Cloudflare is handed the country before any code runs; paying a
    // metered API for it is paying for something it was given.
    $edge = offlineCountry('edge', 'KE');
    $remote = remoteEverything();

    app(IpIntelManager::class)
        ->extend('test-edge', fn (): ResolvesCountry => $edge)
        ->extend('test-remote', fn (): object => $remote);

    config()->set('laranail.ip-intel.chain', ['test-edge', 'test-remote']);

    $result = IpIntel::country('8.8.8.8');

    expect($result->countryCode)->toBe('KE')
        ->and($result->madeNetworkCall)->toBeFalse()
        ->and($remote->calls)->toBe(0);
});

it('reaches the remote source when the question needs it', function (): void {
    // Capability is a type: the offline source cannot answer for an ASN, so
    // the chain does not waste a step asking it.
    $edge = offlineCountry('edge', 'KE');
    $remote = remoteEverything();

    app(IpIntelManager::class)
        ->extend('test-edge', fn (): ResolvesCountry => $edge)
        ->extend('test-remote', fn (): object => $remote);

    config()->set('laranail.ip-intel.chain', ['test-edge', 'test-remote']);

    $result = IpIntel::full('8.8.8.8');

    expect($result->countryCode)->toBe('KE')
        ->and($result->asn?->number)->toBe(64500)
        ->and($result->madeNetworkCall)->toBeTrue();
});

it('falls through to the next source when the first has no answer', function (): void {
    $empty = offlineCountry('empty', null);
    $answers = offlineCountry('answers', 'FR');

    app(IpIntelManager::class)
        ->extend('a', fn (): ResolvesCountry => $empty)
        ->extend('b', fn (): ResolvesCountry => $answers);

    config()->set('laranail.ip-intel.chain', ['a', 'b']);

    expect(IpIntel::country('8.8.8.8')->countryCode)->toBe('FR')
        ->and($empty->calls)->toBe(1);
});

// -----------------------------------------------------------------------
// Outcomes: five cases where the original had one null
// -----------------------------------------------------------------------

it('answers a reserved address without asking any source', function (string $ip): void {
    // 10.0.0.1 is in use on millions of networks in every country there is.
    $source = offlineCountry('x', 'KE');
    app(IpIntelManager::class)->extend('x', fn (): ResolvesCountry => $source);
    config()->set('laranail.ip-intel.chain', ['x']);

    $result = IpIntel::country($ip);

    expect($result->outcome)->toBe(Outcome::Reserved)
        ->and($result->countryCode)->toBeNull()
        ->and($source->calls)->toBe(0);
})->with(['10.0.0.1', '127.0.0.1', '172.17.0.1', '192.168.1.1', '::1', 'fd00::1']);

it('separates a registry gap from a broken source', function (): void {
    // The distinction the original could not express: both were null.
    $silent = offlineCountry('silent', null);
    app(IpIntelManager::class)->extend('silent', fn (): ResolvesCountry => $silent);
    config()->set('laranail.ip-intel.chain', ['silent']);

    expect(IpIntel::country('8.8.8.8')->outcome)->toBe(Outcome::NotFound);

    $broken = new class implements ResolvesCountry
    {
        public function name(): string
        {
            return 'broken';
        }

        public function isAvailable(): bool
        {
            return true;
        }

        public function isRemote(): bool
        {
            return true;
        }

        public function country(IpAddress $address): ?string
        {
            throw SourceUnavailable::rejected('broken', 'the key is dead');
        }
    };

    app(IpIntelManager::class)->extend('broken', fn (): ResolvesCountry => $broken);
    config()->set('laranail.ip-intel.chain', ['broken']);

    $result = IpIntel::country('8.8.8.8');

    expect($result->outcome)->toBe(Outcome::Unavailable)
        ->and($result->needsAttention())->toBeTrue()
        ->and($result->message)->toContain('key is dead');
});

it('reports a disabled feature rather than an unknown address', function (): void {
    config()->set('laranail.ip-intel.enabled', false);

    $result = IpIntel::country('8.8.8.8');

    expect($result->outcome)->toBe(Outcome::Disabled)
        ->and($result->needsAttention())->toBeFalse();
});

it('keeps a broken source from hiding a working one', function (): void {
    $broken = new class implements ResolvesCountry
    {
        public function name(): string
        {
            return 'broken';
        }

        public function isAvailable(): bool
        {
            return true;
        }

        public function isRemote(): bool
        {
            return true;
        }

        public function country(IpAddress $address): ?string
        {
            throw SourceUnavailable::badStatus('broken', 500);
        }
    };

    app(IpIntelManager::class)
        ->extend('broken', fn (): ResolvesCountry => $broken)
        ->extend('good', fn (): ResolvesCountry => offlineCountry('good', 'DE'));

    config()->set('laranail.ip-intel.chain', ['broken', 'good']);

    expect(IpIntel::country('8.8.8.8')->countryCode)->toBe('DE');
});

// -----------------------------------------------------------------------
// Enrichment from atlas
// -----------------------------------------------------------------------

it('fills the country record from atlas rather than from the provider', function (): void {
    // Name, flag, currencies and continent are catalogue facts we already hold
    // offline. Paying an API for them would be absurd.
    app(IpIntelManager::class)->extend('x', fn (): ResolvesCountry => offlineCountry('x', 'KE'));
    config()->set('laranail.ip-intel.chain', ['x']);

    $result = IpIntel::country('8.8.8.8');

    expect($result->country?->name)->toBe('Kenya')
        ->and($result->country?->flag())->toBe('🇰🇪')
        ->and($result->country?->currencies)->toContain('KES');
});

// -----------------------------------------------------------------------
// Caching
// -----------------------------------------------------------------------

it('never caches a failure', function (): void {
    // Caching a dead key's answer for a day means the outage outlives the fix,
    // and the person who fixes it sees no change.
    expect(Outcome::Unavailable->isCacheable())->toBeFalse()
        ->and(Outcome::Disabled->isCacheable())->toBeFalse()
        ->and(Outcome::Found->isCacheable())->toBeTrue()
        ->and(Outcome::Reserved->isCacheable())->toBeTrue()
        ->and(Outcome::NotFound->isCacheable())->toBeTrue();
});

it('serves a repeat lookup from cache', function (): void {
    config()->set('laranail.ip-intel.cache.enabled', true);

    $source = offlineCountry('x', 'KE');
    app(IpIntelManager::class)->extend('x', fn (): ResolvesCountry => $source);
    config()->set('laranail.ip-intel.chain', ['x']);

    IpIntel::country('8.8.8.8');
    IpIntel::country('8.8.8.8');

    expect($source->calls)->toBe(1);
});

// -----------------------------------------------------------------------
// The edge header driver
// -----------------------------------------------------------------------

it('reads the country the reverse proxy supplied', function (string $header): void {
    $request = Request::create('/', 'GET', server: ['REMOTE_ADDR' => '8.8.8.8', 'HTTP_' . strtoupper(str_replace('-', '_', $header)) => 'KE']);
    app()->instance('request', $request);

    config()->set('laranail.ip-intel.chain', ['edge']);

    expect(IpIntel::forRequest($request)->countryCode)->toBe('KE');
})->with(['CF-IPCountry', 'CloudFront-Viewer-Country', 'X-Vercel-IP-Country', 'Fastly-Geo-Country']);

it('ignores the sentinel values a proxy sends when it cannot place an address', function (string $value): void {
    // Cloudflare sends XX for unknown and T1 for Tor. Both pass a naive
    // two-letter check and neither is a country.
    $request = Request::create('/', 'GET', server: ['REMOTE_ADDR' => '8.8.8.8', 'HTTP_CF_IPCOUNTRY' => $value]);
    app()->instance('request', $request);

    config()->set('laranail.ip-intel.chain', ['edge']);

    expect(IpIntel::forRequest($request)->countryCode)->toBeNull();
})->with(['XX', 'T1', '', 'KEN', '1E']);

it('does not answer about an address other than the one that sent the request', function (): void {
    // A header describes the client that sent it. Using it for a different
    // address would be confidently wrong rather than merely missing.
    $request = Request::create('/', 'GET', server: ['REMOTE_ADDR' => '8.8.8.8', 'HTTP_CF_IPCOUNTRY' => 'KE']);
    app()->instance('request', $request);

    config()->set('laranail.ip-intel.chain', ['edge']);

    expect(IpIntel::country('1.1.1.1')->countryCode)->toBeNull();
});
