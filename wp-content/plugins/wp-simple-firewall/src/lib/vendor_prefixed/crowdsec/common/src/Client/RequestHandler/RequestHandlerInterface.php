<?php
/**
 * @license MIT
 *
 * Modified by Paul Goodchild on 15-May-2025 using {@see https://github.com/BrianHenryIE/strauss}.
 */

declare(strict_types=1);

namespace AptowebDeps\CrowdSec\Common\Client\RequestHandler;

use AptowebDeps\CrowdSec\Common\Client\ClientException;
use AptowebDeps\CrowdSec\Common\Client\HttpMessage\Request;
use AptowebDeps\CrowdSec\Common\Client\HttpMessage\Response;
use AptowebDeps\CrowdSec\Common\Client\TimeoutException;

/**
 * Request handler interface.
 *
 * @author    CrowdSec team
 *
 * @see      https://crowdsec.net CrowdSec Official Website
 *
 * @copyright Copyright (c) 2022+ CrowdSec
 * @license   MIT License
 */
interface RequestHandlerInterface
{
    /**
     * Performs an HTTP request and returns a response.
     *
     * @throws ClientException
     * @throws TimeoutException
     */
    public function handle(Request $request): Response;
}
