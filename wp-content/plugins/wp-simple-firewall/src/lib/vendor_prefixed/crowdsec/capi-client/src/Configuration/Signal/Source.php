<?php
/**
 * @license MIT
 *
 * Modified by Paul Goodchild on 25-November-2024 using {@see https://github.com/BrianHenryIE/strauss}.
 */

declare(strict_types=1);

namespace AptowebDeps\CrowdSec\CapiClient\Configuration\Signal;

use AptowebDeps\CrowdSec\Common\Configuration\AbstractConfiguration;
use AptowebDeps\Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use AptowebDeps\Symfony\Component\Config\Definition\Builder\TreeBuilder;

/**
 * The Signal source configuration.
 *
 * @author    CrowdSec team
 *
 * @see      https://crowdsec.net CrowdSec Official Website
 *
 * @copyright Copyright (c) 2022+ CrowdSec
 * @license   MIT License
 */
class Source extends AbstractConfiguration
{
    /**
     * @var string[]
     */
    protected $keys = [
        'scope',
        'value',
        'latitude',
        'longitude',
        'cn',
        'as_name',
        'as_number',
    ];

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('signalSourceConfig');
        /** @var ArrayNodeDefinition $rootNode */
        $rootNode = $treeBuilder->getRootNode();
        $rootNode->children()
            ->scalarNode('scope')->isRequired()->cannotBeEmpty()->end()
            ->scalarNode('value')->isRequired()->cannotBeEmpty()->end()
            ->floatNode('latitude')->end()
            ->floatNode('longitude')->end()
            ->scalarNode('cn')->end()
            ->scalarNode('as_name')->end()
            ->scalarNode('as_number')->end()
        ->end()
        ;

        return $treeBuilder;
    }
}
