<?php

declare(strict_types=1);

namespace Pumukit\TeamsBundle\Tests\Routing;

use PHPUnit\Framework\TestCase;
use Pumukit\TeamsBundle\Routing\RouteLoader;

/**
 * @internal
 *
 * @coversNothing
 */
class RouteLoaderTest extends TestCase
{
    public function testNoRouteIsLoadedWhenItIsDisabled(): void
    {
        $this->assertCount(0, new RouteLoader(false)->load('@PumukitTeamsBundle/Controller/', RouteLoader::TYPE));
    }

    public function testItOnlySupportsItsType(): void
    {
        $loader = new RouteLoader(true);

        $this->assertTrue($loader->supports('@PumukitTeamsBundle/Controller/', RouteLoader::TYPE));
        $this->assertFalse($loader->supports('@PumukitTeamsBundle/Controller/', 'attribute'));
    }
}
