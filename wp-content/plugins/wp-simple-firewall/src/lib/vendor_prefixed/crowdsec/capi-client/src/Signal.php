<?php
/**
 * @license MIT
 *
 * Modified by Paul Goodchild on 25-November-2024 using {@see https://github.com/BrianHenryIE/strauss}.
 */

declare(strict_types=1);

namespace AptowebDeps\CrowdSec\CapiClient;

use AptowebDeps\CrowdSec\CapiClient\Configuration\Signal as SignalConfig;
use AptowebDeps\CrowdSec\CapiClient\Configuration\Signal\Decisions as SignalDecisionsConfig;
use AptowebDeps\CrowdSec\CapiClient\Configuration\Signal\Source as SignalSourceConfig;
use AptowebDeps\Symfony\Component\Config\Definition\Processor;

/**
 * The Signal class.
 *
 * @author    CrowdSec team
 *
 * @see     https://crowdsec.net CrowdSec Official Website
 * @see     https://crowdsecurity.github.io/api_doc/index.html?urls.primaryName=CAPI#/watchers/post_signals
 * @see     https://crowdsecurity.github.io/capi/v2/swagger.yaml
 *
 * @copyright Copyright (c) 2022+ CrowdSec
 * @license   MIT License
 */
class Signal
{
    /**
     * @var array
     */
    private $decisions;
    /**
     * @var array
     */
    private $properties;
    /**
     * @var array
     */
    private $source;

    public function __construct(
        array $properties,
        array $source,
        array $decisions = []
    ) {
        $this->configureProperties($properties);
        $this->configureSource($source);
        $this->configureDecisions($decisions);
    }

    public function toArray(): array
    {
        return $this->properties + [
            'decisions' => $this->decisions,
            'source' => $this->source,
        ];
    }

    private function configureDecisions(array $decisions): void
    {
        $configuration = new SignalDecisionsConfig();
        $processor = new Processor();
        $this->decisions = $processor->processConfiguration($configuration, [$configuration->cleanConfigs($decisions)]);
    }

    private function configureProperties(array $properties): void
    {
        $configuration = new SignalConfig();
        $processor = new Processor();
        $this->properties = $processor->processConfiguration(
            $configuration,
            [$configuration->cleanConfigs($properties)]
        );
    }

    private function configureSource(array $source): void
    {
        $configuration = new SignalSourceConfig();
        $processor = new Processor();
        $this->source = $processor->processConfiguration($configuration, [$configuration->cleanConfigs($source)]);
    }
}
