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

final class BoolTag implements ImplicitTagInterface
{
    /**
     * {@inheritdoc}
     */
    public function construct($value, $implicit = false)
    {
        if (!is_string($value) || (!$implicit && !$this->recognize($value))) {
            throw new \LogicException('not a bool');
        }

        if ('t' === $value[0] || 'T' === $value[0]) {
            return true;
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function recognize($value)
    {
        $valueLower = strtolower($value);
        if ('true' === $valueLower || 'false' === $valueLower) {
            return true;
        }

        return false;
    }
}
