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

namespace AptowebDeps\Symfony\Component\Uid;

/**
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class NilUuid extends Uuid
{
    protected const TYPE = -1;

    public function __construct()
    {
        $this->uid = parent::NIL;
    }
}
