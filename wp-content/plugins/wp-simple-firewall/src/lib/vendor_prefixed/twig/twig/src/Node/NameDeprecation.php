<?php

/*
 * This file is part of Twig.
 *
 * (c) Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * Modified by Paul Goodchild on 25-November-2024 using {@see https://github.com/BrianHenryIE/strauss}.
 */

namespace AptowebDeps\Twig\Node;

/**
 * Represents a deprecation for a named node or attribute on a Node.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class NameDeprecation
{
    private $package;
    private $version;
    private $newName;

    public function __construct(string $package = '', string $version = '', string $newName = '')
    {
        $this->package = $package;
        $this->version = $version;
        $this->newName = $newName;
    }

    public function getPackage(): string
    {
        return $this->package;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function getNewName(): string
    {
        return $this->newName;
    }
}
