<?php
/**
 * @license MIT
 *
 * Modified by Paul Goodchild on 25-November-2024 using {@see https://github.com/BrianHenryIE/strauss}.
 */

declare(strict_types=1);

namespace AptowebDeps\CrowdSec\CapiClient\Client;

use AptowebDeps\CrowdSec\CapiClient\Client\CapiHandler\CapiHandlerInterface;
use AptowebDeps\CrowdSec\CapiClient\Client\CapiHandler\Curl;
use AptowebDeps\CrowdSec\Common\Client\AbstractClient as CommonAbstractClient;
use Psr\Log\LoggerInterface;

/**
 * The low level CrowdSec CAPI Client.
 *
 * @author    CrowdSec team
 *
 * @see      https://crowdsec.net CrowdSec Official Website
 *
 * @copyright Copyright (c) 2022+ CrowdSec
 * @license   MIT License
 */
abstract class AbstractClient extends CommonAbstractClient
{
    /**
     * @var CapiHandlerInterface
     */
    private $capiHandler;

    public function __construct(
        array $configs,
        ?CapiHandlerInterface $listHandler = null,
        ?LoggerInterface $logger = null
    ) {
        $this->configs = $configs;
        $this->capiHandler = ($listHandler) ?: new Curl($this->configs);
        parent::__construct($configs, $this->capiHandler, $logger);
    }

    public function getCapiHandler(): CapiHandlerInterface
    {
        return $this->capiHandler;
    }
}
