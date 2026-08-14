<?php

declare(strict_types=1);

use Simtabi\Laranail\Atlas\Core\Network\IpAddress;
use Simtabi\Laranail\IpIntel\Contracts\ResolvesCountry;
use Simtabi\Laranail\IpIntel\Exceptions\SourceUnavailable;
use Simtabi\Laranail\IpIntel\Providers\IpIntelServiceProvider;
use Simtabi\Laranail\IpIntel\Services\IpIntelManager;

function apiSource(?string $answer = 'KE'): ResolvesCountry
{
    return new readonly class($answer) implements ResolvesCountry
    {
        public function __construct(private ?string $answer) {}

        public function name(): string
        {
            return 'test';
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
            return $this->answer;
        }
    };
}

it('registers no routes while the api is disabled', function (): void {
    // Off means absent, not registered-then-blocked. An endpoint that appears
    // in route:list while "disabled" is one loosened middleware group away
    // from being live, and nobody reviewing that change would look here.
    expect(config('laranail.ip-intel.api.enabled'))->toBeFalse();

    $routes = collect(app('router')->getRoutes()->getRoutes())
        ->filter(fn ($route): bool => str_contains((string) $route->uri(), 'ip-intel'));

    expect($routes)->toBeEmpty();
});

describe('with the api enabled', function (): void {
    beforeEach(function (): void {
        config()->set('laranail.ip-intel.api.enabled', true);
        config()->set('laranail.ip-intel.api.middleware', ['api']);
        config()->set('laranail.ip-intel.cache.enabled', false);

        // Re-register so the routes are added; the provider only loads them
        // when the flag is on at boot.
        app()->register(IpIntelServiceProvider::class, force: true);

        app(IpIntelManager::class)->extend('test', fn (): ResolvesCountry => apiSource());
        config()->set('laranail.ip-intel.chain', ['test']);
    });

    it('answers a lookup', function (): void {
        $this->getJson('api/ip-intel/v1/lookup?ip=8.8.8.8')
            ->assertOk()
            ->assertJsonPath('data.country_code', 'KE')
            ->assertJsonPath('data.outcome', 'found')
            ->assertJsonPath('data.country', 'Kenya');
    });

    it('rejects an address that is not one', function (string $ip): void {
        // A 422 naming the field, not a 500 from somewhere inside a driver.
        $this->getJson('api/ip-intel/v1/lookup?ip=' . urlencode($ip))
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('ip');
    })->with(['not-an-ip', '01.02.03.04', '256.1.1.1', '']);

    it('requires an address', function (): void {
        $this->getJson('api/ip-intel/v1/lookup')
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('ip');
    });

    it('reports a reserved address as a successful answer', function (): void {
        // 200: the question was well-formed and the answer is "nowhere". A 404
        // would say the address does not exist.
        $this->getJson('api/ip-intel/v1/lookup?ip=10.0.0.1')
            ->assertOk()
            ->assertJsonPath('data.outcome', 'reserved')
            ->assertJsonPath('data.country_code', null);
    });

    it('answers 503 when a source is down', function (): void {
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

        app(IpIntelManager::class)->extend('broken', fn (): ResolvesCountry => $broken);
        config()->set('laranail.ip-intel.chain', ['broken']);

        // Retryable and server-side — not a 200 with an empty body, which a
        // client would cache as "this address is unknown".
        $this->getJson('api/ip-intel/v1/lookup?ip=8.8.8.8')
            ->assertStatus(503)
            ->assertJsonPath('data.outcome', 'unavailable');
    });

    it('answers the caller about itself', function (): void {
        $this->getJson('api/ip-intel/v1/me')
            ->assertOk()
            ->assertJsonPath('data.outcome', 'reserved');
    });

    it('is read-only', function (): void {
        $this->postJson('api/ip-intel/v1/lookup', ['ip' => '8.8.8.8'])->assertStatus(405);
    });

    it('honours a configured prefix', function (): void {
        config()->set('laranail.ip-intel.api.prefix', 'internal/geo');
        app()->register(IpIntelServiceProvider::class, force: true);

        $this->getJson('internal/geo/v1/lookup?ip=8.8.8.8')->assertOk();
    });
});
