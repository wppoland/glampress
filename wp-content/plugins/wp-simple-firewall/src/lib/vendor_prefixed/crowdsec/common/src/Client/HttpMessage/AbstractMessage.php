<?php
/**
 * @license MIT
 *
 * Modified by Paul Goodchild on 15-May-2025 using {@see https://github.com/BrianHenryIE/strauss}.
 */

declare(strict_types=1);

namespace AptowebDeps\CrowdSec\Common\Client\HttpMessage;

/**
 * HTTP messages consist of requests from a client to CrowdSec and responses
 * from CrowdSec to a client.
 *
 * @author    CrowdSec team
 *
 * @see      https://crowdsec.net CrowdSec Official Website
 *
 * @copyright Copyright (c) 2022+ CrowdSec
 * @license   MIT License
 */
abstract class AbstractMessage
{
    /**
     * @var array
     */
    protected $headers = [];

    public function getHeaders(): array
    {
        return $this->headers;
    }
}
