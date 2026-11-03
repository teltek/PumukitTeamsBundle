<?php

declare(strict_types=1);

namespace Pumukit\TeamsBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public const JWKS_URI = 'https://login.microsoftonline.com/common/discovery/v2.0/keys';

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('pumukit_teams');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->validate()
            ->ifTrue(static fn (array $config): bool => $config['enable'] && [] !== self::missing($config))
            ->then(static function (array $config): never {
                throw new \InvalidArgumentException('pumukit_teams needs '.implode(', ', self::missing($config)).' when it is enabled');
            })
            ->end()
            ->children()
            ->booleanNode('enable')
            ->defaultTrue()
            ->info('If false, the Teams API gives 404 and client_id and tenants are not required')
            ->end()
            ->scalarNode('client_id')
            ->defaultNull()
            ->info('Application (client) ID of the Azure AD app registration of the PuMuKIT Teams app')
            ->end()
            ->arrayNode('tenants')
            ->info('Azure AD tenant IDs whose users can import recordings')
            ->scalarPrototype()->cannotBeEmpty()->end()
            ->end()
            ->scalarNode('jwks_uri')
            ->defaultValue(self::JWKS_URI)
            ->cannotBeEmpty()
            ->info('URL of the public keys that sign the Teams tokens')
            ->end()
            ->scalarNode('profile')
            ->defaultValue('master_copy')
            ->cannotBeEmpty()
            ->info('Encoder profile of the imported recordings')
            ->end()
            ->integerNode('priority')
            ->defaultValue(2)
            ->min(0)
            ->info('Priority of the encoder jobs of the imported recordings')
            ->end()
            ->scalarNode('language')
            ->defaultNull()
            ->info('Language of the imported tracks. If not defined, it takes the default locale')
            ->end()
            ->end()
        ;

        return $treeBuilder;
    }

    private static function missing(array $config): array
    {
        return array_values(array_filter([
            '' === trim((string) $config['client_id']) ? 'client_id' : null,
            [] === $config['tenants'] ? 'tenants' : null,
        ]));
    }
}
