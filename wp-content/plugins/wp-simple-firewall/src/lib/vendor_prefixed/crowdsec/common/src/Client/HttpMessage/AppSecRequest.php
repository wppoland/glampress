<?php
/**
 * @license MIT
 *
 * Modified by Paul Goodchild on 15-May-2025 using {@see https://github.com/BrianHenryIE/strauss}.
 */

declare(strict_types=1);

namespace AptowebDeps\CrowdSec\Common\Client\HttpMessage;

use AptowebDeps\CrowdSec\Common\Constants;

/**
 * Request that will be sent to CrowdSec AppSec component.
 *
 * @author    CrowdSec team
 *
 * @see      https://crowdsec.net CrowdSec Official Website
 *
 * @copyright Copyright (c) 2024+ CrowdSec
 * @license   MIT License
 */
class AppSecRequest extends Request
{
    /**
     * @var array
     */
    protected $headers = [];
    /**
     * @var string[]
     */
    protected $requiredHeaders = [
        Constants::HEADER_APPSEC_IP,
        Constants::HEADER_APPSEC_USER_AGENT,
        Constants::HEADER_APPSEC_VERB,
        Constants::HEADER_APPSEC_URI,
        Constants::HEADER_APPSEC_HOST,
        Constants::HEADER_APPSEC_API_KEY,
    ];
    /**
     * @var string
     */
    private $rawBody;

    public function __construct(
        string $uri,
        string $method,
        array $headers = [],
        string $rawBody = ''
    ) {
        $this->rawBody = $rawBody;
        parent::__construct($uri, $method, $headers);
    }

    public function getRawBody(): string
    {
        return $this->rawBody;
    }
}
