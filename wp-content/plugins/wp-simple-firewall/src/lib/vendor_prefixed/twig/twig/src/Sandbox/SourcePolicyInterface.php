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

namespace AptowebDeps\Twig\Sandbox;

use AptowebDeps\Twig\Source;

/**
 * Interface for a class that can optionally enable the sandbox mode based on a template's Twig\Source.
 *
 * @author Yaakov Saxon
 */
interface SourcePolicyInterface
{
    public function enableSandbox(Source $source): bool;
}
