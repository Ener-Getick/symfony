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

final class NullTag implements ImplicitTagInterface
{
    /**
     * {@inheritdoc}
     */
    public function construct($value, $implicit = false)
    {
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function recognize($value)
    {
        if ('' === $value || '~' === $value) {
            return true;
        }

        $valueLower = strtolower($value);

        return 'null' === $valueLower;
    }
}
