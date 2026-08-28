<?php

declare(strict_types=1);

namespace Simtabi\Laranail\IpIntel\Tests;

use Simtabi\Laranail\Atlas\Providers\AtlasServiceProvider;
use Simtabi\Laranail\IpIntel\Providers\IpIntelServiceProvider;
use Simtabi\Laranail\Package\Tools\Testing\IsolatedTestCase;

abstract class TestCase extends IsolatedTestCase
{
    /**
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            AtlasServiceProvider::class,
            IpIntelServiceProvider::class,
        ];
    }
}
