<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * Modified by Paul Goodchild on 24-February-2025 using {@see https://github.com/BrianHenryIE/strauss}.
 */

namespace AptowebDeps\Symfony\Component\Config\Util\Exception;

/**
 * Exception class for when XML parsing with an XSD schema file path or a callable validator produces errors unrelated
 * to the actual XML parsing.
 *
 * @author Ole Rößner <ole@roessner.it>
 */
class InvalidXmlException extends XmlParsingException
{
}
