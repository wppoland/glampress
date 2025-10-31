<?php
/**
 * @license MIT
 *
 * Modified by Paul Goodchild on 15-May-2025 using {@see https://github.com/BrianHenryIE/strauss}.
 */

declare(strict_types=1);

namespace AptowebDeps\CrowdSec\Common\Logger;

use AptowebDeps\Monolog\Formatter\LineFormatter;
use AptowebDeps\Monolog\Handler\StreamHandler;
use AptowebDeps\Monolog\Logger;

/**
 * A Monolog logger implementation to print log in terminal.
 *
 * @author    CrowdSec team
 *
 * @see      https://crowdsec.net CrowdSec Official Website
 *
 * @copyright Copyright (c) 2022+ CrowdSec
 * @license   MIT License
 */
class ConsoleLog extends AbstractLog
{
    /**
     * @var string The logger name
     */
    public const LOGGER_NAME = 'common-console-logger';

    public function __construct(array $configs = [], string $name = self::LOGGER_NAME)
    {
        parent::__construct($configs, $name);
        $levelFallback = Logger::API >= 3 ? constant('AptowebDeps\Monolog\Level::Debug')->value : AbstractLog::DEBUG;
        $level = $configs['level'] ?? $levelFallback;
        $handler = new StreamHandler('php://stdout', $level);
        $handler->setFormatter(new LineFormatter($this->format));
        $this->getMonologLogger()->pushHandler($handler);
    }
}
