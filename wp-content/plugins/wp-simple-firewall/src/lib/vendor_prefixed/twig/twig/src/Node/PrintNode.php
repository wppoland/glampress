<?php

/*
 * This file is part of Twig.
 *
 * (c) Fabien Potencier
 * (c) Armin Ronacher
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * Modified by Paul Goodchild on 25-November-2024 using {@see https://github.com/BrianHenryIE/strauss}.
 */

namespace AptowebDeps\Twig\Node;

use AptowebDeps\Twig\Attribute\YieldReady;
use AptowebDeps\Twig\Compiler;
use AptowebDeps\Twig\Node\Expression\AbstractExpression;

/**
 * Represents a node that outputs an expression.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
#[YieldReady]
class PrintNode extends Node implements NodeOutputInterface
{
    public function __construct(AbstractExpression $expr, int $lineno, ?string $tag = null)
    {
        parent::__construct(['expr' => $expr], [], $lineno, $tag);
    }

    public function compile(Compiler $compiler): void
    {
        /** @var AbstractExpression */
        $expr = $this->getNode('expr');

        $compiler
            ->addDebugInfo($this)
            ->write($expr->isGenerator() ? 'yield from ' : 'yield ')
            ->subcompile($expr)
            ->raw(";\n")
        ;
    }
}
