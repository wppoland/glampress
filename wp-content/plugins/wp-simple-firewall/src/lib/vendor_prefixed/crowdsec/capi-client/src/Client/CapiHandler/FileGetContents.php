<?php
/**
 * @license MIT
 *
 * Modified by Paul Goodchild on 25-November-2024 using {@see https://github.com/BrianHenryIE/strauss}.
 */

declare(strict_types=1);

namespace AptowebDeps\CrowdSec\CapiClient\Client\CapiHandler;

use AptowebDeps\CrowdSec\Common\Client\RequestHandler\FileGetContents as CommonFileGetContents;
use AptowebDeps\CrowdSec\Common\Constants;

/**
 * FileGetContents list handler to get CAPI linked decisions (blocklists).
 *
 * @author    CrowdSec team
 *
 * @see      https://crowdsec.net CrowdSec Official Website
 *
 * @copyright Copyright (c) 2022+ CrowdSec
 * @license   MIT License
 */
class FileGetContents extends CommonFileGetContents implements CapiHandlerInterface
{
    public function getListDecisions(string $url, array $headers = []): string
    {
        $config = $this->createListContextConfig($headers);
        $context = stream_context_create($config);

        $fullResponse = $this->exec($url, $context);
        $response = (isset($fullResponse['response'])) ? $fullResponse['response'] : false;
        $responseHeaders = (isset($fullResponse['header'])) ? $fullResponse['header'] : [];
        $parts = !empty($responseHeaders) ? explode(' ', $responseHeaders[0]) : [];
        $status = $this->getResponseHttpCode($parts);

        return 200 === $status ? (string) $response : '';
    }

    private function createListContextConfig(array $headers = []): array
    {
        $header = $this->convertHeadersToString($headers);

        return [
            'http' => [
                'method' => 'GET',
                'header' => $header,
                'ignore_errors' => true,
                'timeout' => Constants::API_TIMEOUT,
            ],
        ];
    }
}
