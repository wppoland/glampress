<?php
/**
 * @license MIT
 *
 * Modified by Paul Goodchild on 25-November-2024 using {@see https://github.com/BrianHenryIE/strauss}.
 */

declare(strict_types=1);

namespace AptowebDeps\CrowdSec\CapiClient;

use AptowebDeps\CrowdSec\Common\Client\ClientException as CommonClientException;

/**
 * Exception interface for all exceptions thrown by CrowdSec CAPI Client.
 *
 * @author    CrowdSec team
 *
 * @see      https://crowdsec.net CrowdSec Official Website
 *
 * @copyright Copyright (c) 2022+ CrowdSec
 * @license   MIT License
 */
class ClientException extends CommonClientException
{
}
