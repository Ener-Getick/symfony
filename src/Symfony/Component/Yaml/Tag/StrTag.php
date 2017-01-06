<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Yaml\Tag;

final class StrTag implements ImplicitTagInterface
{
    /**
     * {@inheritdoc}
     */
    public function construct($value, $implicit = false)
    {
        if (!is_string($value)) {
            throw new \LogicException('Not a string');
        }

        return $value;
    }

    /**
     * {@inheritdoc}
     */
    public function recognize($value)
    {
        return true;
    }
}
