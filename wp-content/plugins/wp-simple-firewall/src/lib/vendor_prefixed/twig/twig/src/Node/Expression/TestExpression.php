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

namespace AptowebDeps\Twig\Node\Expression;

use AptowebDeps\Twig\Compiler;
use AptowebDeps\Twig\Node\Node;

class TestExpression extends CallExpression
{
    public function __construct(Node $node, string $name, ?Node $arguments, int $lineno)
    {
        $nodes = ['node' => $node];
        if (null !== $arguments) {
            $nodes['arguments'] = $arguments;
        }

        parent::__construct($nodes, ['name' => $name, 'type' => 'test'], $lineno);
    }

    public function compile(Compiler $compiler): void
    {
        $test = $compiler->getEnvironment()->getTest($this->getAttribute('name'));

        $this->setAttribute('arguments', $test->getArguments());
        $this->setAttribute('callable', $test->getCallable());
        $this->setAttribute('is_variadic', $test->isVariadic());

        $this->compileCallable($compiler);
    }
}
