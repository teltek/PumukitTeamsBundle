<?php

declare(strict_types=1);

namespace Pumukit\TeamsBundle\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Pumukit\TeamsBundle\Controller\APIController;
use Pumukit\TeamsBundle\DependencyInjection\PumukitTeamsExtension;
use Pumukit\TeamsBundle\PumukitTeamsBundle;
use Pumukit\TeamsBundle\Routing\RouteLoader;
use Pumukit\TeamsBundle\Services\ImportService;
use Pumukit\TeamsBundle\Services\TeamsAuthenticator;
use Pumukit\TeamsBundle\Services\TokenValidator;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal
 *
 * @coversNothing
 */
class PumukitTeamsExtensionTest extends TestCase
{
    private const ENABLED = ['client_id' => 'app', 'tenants' => ['tenant']];

    public function testTheDisabledBundleOnlyRegistersTheRouteLoader(): void
    {
        $container = $this->load(['enable' => false]);

        $this->assertSame([RouteLoader::class], $this->teamsServices($container));
        $this->assertSame('%pumukit_teams.enable%', $container->getDefinition(RouteLoader::class)->getArgument('$enabled'));
        $this->assertTrue($container->getDefinition(RouteLoader::class)->hasTag('routing.loader'));
        $this->assertSame([], $container->getParameter('pumukitschema.external_permissions'));
    }

    public function testTheEnabledBundleRegistersItsServicesAndThePermission(): void
    {
        $container = $this->load(self::ENABLED);

        foreach ([APIController::class, TokenValidator::class, TeamsAuthenticator::class, ImportService::class, RouteLoader::class] as $id) {
            $this->assertTrue($container->hasDefinition($id), $id);
        }
        $this->assertSame([['role' => PumukitTeamsBundle::PERMISSION, 'description' => 'Access the Teams API']], $container->getParameter('pumukitschema.external_permissions'));
    }

    public function testTheCachePoolIsOnlyPrependedWhenTheBundleIsEnabled(): void
    {
        $this->assertSame([], $this->prepend(['enable' => false])->getExtensionConfig('framework'));
        $this->assertSame(
            [['cache' => ['pools' => [PumukitTeamsExtension::CACHE_POOL => ['adapter' => 'cache.app']]]]],
            $this->prepend(self::ENABLED)->getExtensionConfig('framework'),
        );
    }

    private function load(array $config): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('pumukitschema.external_permissions', []);
        new PumukitTeamsExtension()->load([$config], $container);

        return $container;
    }

    private function prepend(array $config): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->prependExtensionConfig('pumukit_teams', $config);
        new PumukitTeamsExtension()->prepend($container);

        return $container;
    }

    private function teamsServices(ContainerBuilder $container): array
    {
        return array_values(array_filter(array_keys($container->getDefinitions()), static fn (string $id): bool => str_starts_with($id, 'Pumukit\TeamsBundle\\')));
    }
}
