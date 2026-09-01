<?php

declare(strict_types=1);

namespace Simtabi\Laranail\IpIntel\Providers;

use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Override;
use Psr\Log\LoggerInterface;
use Simtabi\Laranail\Atlas\Core\Contracts\IpCountryResolver;
use Simtabi\Laranail\Atlas\Services\AtlasService;
use Simtabi\Laranail\IpIntel\Adapters\EdgeHeader\EdgeHeaderDriver;
use Simtabi\Laranail\IpIntel\Adapters\IpApi\IpApiDriver;
use Simtabi\Laranail\IpIntel\Adapters\Local\AtlasDriver;
use Simtabi\Laranail\IpIntel\Services\IpIntelManager;
use Simtabi\Laranail\IpIntel\Support\IpIntelConfig;
use Simtabi\Laranail\Package\Tools\Package;
use Simtabi\Laranail\Package\Tools\Providers\PackageServiceProvider;

/**
 * Entry point for laranail/ip-intel.
 *
 * Config is vendor-namespaced by default, so it publishes to
 * `config/laranail/ip-intel.php` and reads as `config('laranail.ip-intel.*')`.
 *
 * @internal Auto-discovered framework wiring; not part of the public API.
 */
final class IpIntelServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laranail/ip-intel')
            ->setPublishTagId('ip-intel')
            ->hasConfigFile('ip-intel');
    }

    #[Override]
    public function packageRegistered(): void
    {
        $this->app->singleton(
            IpIntelConfig::class,
            static fn (Application $app): IpIntelConfig => new IpIntelConfig($app->make(ConfigRepository::class)),
        );

        $this->app->bind(
            EdgeHeaderDriver::class,
            static fn (Application $app): EdgeHeaderDriver => new EdgeHeaderDriver(
                // Bound rather than resolved eagerly: outside a request there is
                // no header to read, and the driver reports itself unavailable.
                $app->bound('request') && $app->make('request') instanceof Request
                    ? $app->make('request')
                    : null,
            ),
        );

        $this->app->bind(
            AtlasDriver::class,
            static fn (Application $app): AtlasDriver => new AtlasDriver(
                $app->make(IpCountryResolver::class),
            ),
        );

        $this->app->bind(IpApiDriver::class, static function (Application $app): IpApiDriver {
            $config = $app->make(IpIntelConfig::class);

            return new IpApiDriver(
                $app->make(HttpFactory::class),
                $app->make(LoggerInterface::class),
                $config->nullableString('sources.ipapi.key'),
                $config->string('sources.ipapi.base_url', 'https://api.ipapi.com/api'),
                $config->int('sources.ipapi.timeout', 5),
                $config->int('sources.ipapi.retries', 2),
            );
        });

        $this->app->singleton(IpIntelManager::class, static function (Application $app): IpIntelManager {
            $config = $app->make(IpIntelConfig::class);

            return new IpIntelManager(
                $app,
                $config,
                $app->make(AtlasService::class),
                $app->make(CacheFactory::class)->store($config->nullableString('cache.store')),
            );
        });
    }

    #[Override]
    public function packageBooted(): void
    {
        $this->registerApiRoutes();
    }

    /**
     * Register the API only when it is switched on.
     *
     * **Off means absent, not registered-then-blocked.** A disabled API that
     * still appears in `route:list` is one loosened middleware group away from
     * being live, and nobody reviewing that change would see this file.
     */
    private function registerApiRoutes(): void
    {
        $config = $this->app->make(IpIntelConfig::class);

        if (! $config->bool('api.enabled', false)) {
            return;
        }

        $middleware = array_values(array_filter(
            $config->array('api.middleware', ['api']),
            is_string(...),
        ));

        Route::group([
            'prefix' => trim($config->string('api.prefix', 'api/ip-intel'), '/')
                .'/'.trim($config->string('api.version', 'v1'), '/'),
            'middleware' => $middleware,
        ], function (): void {
            $this->loadRoutesFrom($this->packagePath('routes/api.php'));
        });
    }
}
