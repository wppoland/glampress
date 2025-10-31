<?php
/**
 * @license MIT
 *
 * Modified by Paul Goodchild on 25-November-2024 using {@see https://github.com/BrianHenryIE/strauss}.
 */ declare(strict_types=1);

/*
 * This file is part of the Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace AptowebDeps\Monolog\Handler;

/**
 * Exception can be thrown if an extension for a handler is missing
 *
 * @author Christian Bergau <cbergau86@gmail.com>
 */
class MissingExtensionException extends \Exception
{
}
