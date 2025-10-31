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

namespace AptowebDeps\Symfony\Component\Uid\Factory;

use AptowebDeps\Symfony\Component\Uid\UuidV4;

class RandomBasedUuidFactory
{
    private $class;

    public function __construct(string $class)
    {
        $this->class = $class;
    }

    public function create(): UuidV4
    {
        $class = $this->class;

        return new $class();
    }
}
