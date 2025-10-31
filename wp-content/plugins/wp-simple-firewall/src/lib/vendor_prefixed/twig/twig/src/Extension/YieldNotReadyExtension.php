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

namespace AptowebDeps\Twig\Extension;

use AptowebDeps\Twig\NodeVisitor\YieldNotReadyNodeVisitor;

/**
 * @internal to be removed in Twig 4
 */
final class YieldNotReadyExtension extends AbstractExtension
{
    private $useYield;

    public function __construct(bool $useYield)
    {
        $this->useYield = $useYield;
    }

    public function getNodeVisitors(): array
    {
        return [new YieldNotReadyNodeVisitor($this->useYield)];
    }
}
