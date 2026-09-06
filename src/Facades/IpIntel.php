<?php

declare(strict_types=1);

namespace Simtabi\Laranail\IpIntel\Facades;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;
use Simtabi\Laranail\IpIntel\Data\IpIntelResult;
use Simtabi\Laranail\Atlas\Core\Network\IpAddress;
use Simtabi\Laranail\IpIntel\Services\ResolverChain;
use Simtabi\Laranail\IpIntel\Services\IpIntelManager;

/**
 * @method static IpIntelResult country(IpAddress|string $address)
 * @method static IpIntelResult full(IpAddress|string $address)
 * @method static IpIntelResult forRequest(?Request $request = null)
 * @method static ResolverChain chain()
 * @method static IpIntelManager extend(string $name, Closure $factory)
 * @method static list<string> available()
 *
 * @see IpIntelManager
 */
final class IpIntel extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return IpIntelManager::class;
    }
}
