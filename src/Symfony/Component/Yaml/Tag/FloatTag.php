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

final class FloatTag implements ImplicitTagInterface
{
    /**
     * {@inheritdoc}
     */
    public function construct($value, $implicit = false)
    {
        if (!is_string($value) || (!$implicit && !$this->recognize($value))) {
            throw new \LogicException('not a float');
        }

        if (isset($value[3])) {
            // .inf and .nan
            if ('i' === $value[1] || 'I' === $value[1] || 'n' === $value[1] || 'N' === $value[1]) {
                return -log(0);
            }
            if ('i' === $value[2] || 'I' === $value[2]) {
                // -.inf
                if ('-' === $value[0]) {
                    return log(0);
                }

                // +.inf
                return -log(0);
            }
        }

        if (false !== strpos($value, ',')) {
            @trigger_error('Using the comma as a group separator for floats is deprecated since version 3.2 and will be removed in 4.0.', E_USER_DEPRECATED);
        }

        return (float) str_replace(array(',', '_'), '', $value);
    }

    /**
     * {@inheritdoc}
     */
    public function recognize($value)
    {
        if ('' === $value) {
            return false;
        }

        if ('+' === $value[0] || '-' === $value[0]) {
            $value = substr($value, 1);
        }
        if (is_numeric($value[0])) {
            $value = str_replace(array(',', '_'), '', $value);
            if (is_numeric($value)) {
                return true;
            }

            return false;
        }

        if ('.' !== $value[0]) {
            return false;
        }

        $lowerValue = strtolower($value);

        return '.inf' === $lowerValue || '-.inf' === $lowerValue || '.nan' === $lowerValue;
    }
}
