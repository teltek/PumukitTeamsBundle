<?php

declare(strict_types=1);

namespace Pumukit\TeamsBundle\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Pumukit\TeamsBundle\DependencyInjection\Configuration;
use Pumukit\TeamsBundle\DependencyInjection\PumukitTeamsExtension;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal
 *
 * @coversNothing
 */
class ConfigurationTest extends TestCase
{
    public function testTheEnabledBundleNeedsTheAppAndTheTenants(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('client_id, tenants');

        $this->process([]);
    }

    public function testTheEnabledBundleWithTheAppAndTheTenantsIsValid(): void
    {
        $config = $this->process(['client_id' => 'app', 'tenants' => ['tenant']]);

        $this->assertTrue($config['enable']);
        $this->assertSame(['tenant'], $config['tenants']);
    }

    public function testTheDisabledBundleNeedsNoAppNorTenants(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('pumukitschema.external_permissions', []);
        $container->setParameter('pumukit.locales', ['en']);
        (new PumukitTeamsExtension())->load([['enable' => false]], $container);

        $this->assertFalse($container->getParameter('pumukit_teams.enable'));
        $this->assertSame('', $container->getParameter('pumukit_teams.client_id'));
        $this->assertSame([], $container->getParameter('pumukit_teams.tenants'));
    }

    private function process(array $config): array
    {
        return (new Processor())->processConfiguration(new Configuration(), [$config]);
    }
}
