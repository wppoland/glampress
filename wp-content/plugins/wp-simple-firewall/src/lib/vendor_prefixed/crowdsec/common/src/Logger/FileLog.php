<?php
/**
 * @license MIT
 *
 * Modified by Paul Goodchild on 15-May-2025 using {@see https://github.com/BrianHenryIE/strauss}.
 */

declare(strict_types=1);

namespace AptowebDeps\CrowdSec\Common\Logger;

use AptowebDeps\Monolog\Formatter\LineFormatter;
use AptowebDeps\Monolog\Handler\HandlerInterface;
use AptowebDeps\Monolog\Handler\RotatingFileHandler;
use AptowebDeps\Monolog\Handler\StreamHandler;
use AptowebDeps\Monolog\Logger;

/**
 * A Monolog logger implementation with 2 files : debug and prod.
 *
 * @author    CrowdSec team
 *
 * @see      https://crowdsec.net CrowdSec Official Website
 *
 * @copyright Copyright (c) 2022+ CrowdSec
 * @license   MIT License
 */

class FileLog extends AbstractLog
{
    /**
     * @var string The debug log filename
     */
    public const DEBUG_FILE = 'debug.log';
    /**
     * @var string The logger name
     */
    public const LOGGER_NAME = 'common-file-logger';
    /**
     * @var string The prod log filename
     */
    public const PROD_FILE = 'prod.log';

    public function __construct(array $configs = [], string $name = self::LOGGER_NAME)
    {
        parent::__construct($configs, $name);
        $logDir = $configs['log_directory_path'] ?? __DIR__ . '/.logs';
        if (empty($configs['disable_prod_log'])) {
            $prodLogPath = $logDir . '/' . self::PROD_FILE;
            $logLevel = Logger::API >= 3 ? constant('AptowebDeps\Monolog\Level::Info')->value : AbstractLog::INFO;
            $fileHandler = $this->buildFileHandler($prodLogPath, $logLevel, $configs);
            $this->getMonologLogger()->pushHandler($fileHandler);
        }

        if (!empty($configs['debug_mode'])) {
            $debugLogPath = $logDir . '/' . self::DEBUG_FILE;
            $logLevel = Logger::API >= 3 ? constant('AptowebDeps\Monolog\Level::Debug')->value : AbstractLog::DEBUG;
            $debugFileHandler = $this->buildFileHandler($debugLogPath, $logLevel, $configs);
            $this->getMonologLogger()->pushHandler($debugFileHandler);
        }
    }

    private function buildFileHandler(string $logFilePath, int $logLevel, array $configs = []): HandlerInterface
    {
        $fileHandler = !empty($configs['log_rotator']) ?
            new RotatingFileHandler($logFilePath, 0, $logLevel) :
            new StreamHandler($logFilePath, $logLevel);

        return $fileHandler->setFormatter(new LineFormatter($this->format));
    }
}
