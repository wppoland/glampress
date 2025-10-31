<?php
/**
 * @license MIT
 *
 * Modified by Paul Goodchild on 25-November-2024 using {@see https://github.com/BrianHenryIE/strauss}.
 */

declare(strict_types=1);

namespace AptowebDeps\CrowdSec\CapiClient\Configuration;

use AptowebDeps\CrowdSec\CapiClient\Constants;
use AptowebDeps\CrowdSec\Common\Configuration\AbstractConfiguration;
use AptowebDeps\Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use AptowebDeps\Symfony\Component\Config\Definition\Builder\TreeBuilder;

/**
 * The Signal configuration.
 *
 * @author    CrowdSec team
 *
 * @see      https://crowdsec.net CrowdSec Official Website
 *
 * @copyright Copyright (c) 2022+ CrowdSec
 * @license   MIT License
 */
class Signal extends AbstractConfiguration
{
    /**
     * @var string[]
     */
    protected $keys = [
        'scenario_trust',
        'scenario_hash',
        'scenario',
        'alert_id',
        'created_at',
        'machine_id',
        'scenario_version',
        'message',
        'start_at',
        'stop_at',
        'uuid',
        'context',
    ];

    /**
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('signalConfig');
        /** @var ArrayNodeDefinition $rootNode */
        $rootNode = $treeBuilder->getRootNode();
        $rootNode->children()
            ->scalarNode('scenario_trust')->defaultValue(Constants::TRUST_MANUAL)->end()
            ->scalarNode('scenario_hash')
                ->isRequired()->defaultValue('')
            ->end()
            ->scalarNode('scenario')
                ->isRequired()->cannotBeEmpty()
                ->validate()
                ->ifTrue(function (string $value) {
                    return 1 !== preg_match(Constants::SCENARIO_REGEX, $value);
                })
                ->thenInvalid('Invalid scenario. Must match with ' . Constants::SCENARIO_REGEX . ' regex')
                ->end()
            ->end()
            ->integerNode('alert_id')->min(0)->end()
            ->scalarNode('uuid')->cannotBeEmpty()->end()
            ->scalarNode('created_at')
                ->cannotBeEmpty()
                ->validate()
                ->ifTrue(function (string $value) {
                    return 1 !== preg_match(Constants::ISO8601_REGEX, $value);
                })
                ->thenInvalid('Invalid created_at. Must match with ' . Constants::ISO8601_REGEX)
                ->end()
            ->end()
            ->scalarNode('machine_id')->cannotBeEmpty()->end()
            ->scalarNode('scenario_version')->isRequired()->defaultValue('')->end()
            ->scalarNode('message')->isRequired()->defaultValue('')->end()
            ->scalarNode('start_at')
                ->isRequired()->cannotBeEmpty()
                ->validate()
                ->ifTrue(function (string $value) {
                    return 1 !== preg_match(Constants::ISO8601_REGEX, $value);
                })
                ->thenInvalid('Invalid start_at. Must match with ' . Constants::ISO8601_REGEX)
                ->end()
            ->end()
            ->scalarNode('stop_at')
                ->isRequired()->cannotBeEmpty()
                ->validate()
                ->ifTrue(function (string $value) {
                    return 1 !== preg_match(Constants::ISO8601_REGEX, $value);
                })
                ->thenInvalid('Invalid stop_at. Must match with ' . Constants::ISO8601_REGEX)
                ->end()
            ->end()
            ->arrayNode('context')
                ->arrayPrototype()
                    ->children()
                        ->scalarNode('key')->isRequired()->cannotBeEmpty()->end()
                        ->scalarNode('value')->isRequired()->end()
                    ->end()
                ->end()
            ->end()
        ->end()
        ;

        return $treeBuilder;
    }
}
