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

final class NonSpecificTag implements TagInterface
{
    /**
     * {@inheritdoc}
     */
    public function construct($value)
    {
        if (is_string($value)) {
            /** @todo must be deprecated */

            return (int) $value;
        }

        return $value;
    }
}
