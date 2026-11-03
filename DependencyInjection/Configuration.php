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
            ->children()
            ->scalarNode('client_id')
            ->isRequired()
            ->cannotBeEmpty()
            ->info('Application (client) ID of the Azure AD app registration of the PuMuKIT Teams app')
            ->end()
            ->arrayNode('tenants')
            ->isRequired()
            ->requiresAtLeastOneElement()
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
}
