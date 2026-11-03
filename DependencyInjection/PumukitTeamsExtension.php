<?php

declare(strict_types=1);

namespace Pumukit\TeamsBundle\DependencyInjection;

use Pumukit\TeamsBundle\PumukitTeamsBundle;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader;

class PumukitTeamsExtension extends Extension implements PrependExtensionInterface
{
    public const CACHE_POOL = 'pumukit_teams.cache';

    public function prepend(ContainerBuilder $container): void
    {
        $container->prependExtensionConfig('framework', [
            'cache' => [
                'pools' => [
                    self::CACHE_POOL => ['adapter' => 'cache.app'],
                ],
            ],
        ]);
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration(new Configuration(), $configs);

        $container->setParameter('pumukit_teams.enable', $config['enable']);
        $container->setParameter('pumukit_teams.client_id', (string) $config['client_id']);
        $container->setParameter('pumukit_teams.tenants', array_values($config['tenants']));
        $container->setParameter('pumukit_teams.jwks_uri', $config['jwks_uri']);
        $container->setParameter('pumukit_teams.profile', $config['profile']);
        $container->setParameter('pumukit_teams.priority', $config['priority']);
        $container->setParameter('pumukit_teams.language', $config['language'] ?? '%kernel.default_locale%');

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('pumukit_teams.yaml');

        $permissions = [['role' => PumukitTeamsBundle::PERMISSION, 'description' => 'Access the Teams API']];
        $newPermissions = array_merge($container->getParameter('pumukitschema.external_permissions'), $permissions);
        $container->setParameter('pumukitschema.external_permissions', $newPermissions);
    }
}
