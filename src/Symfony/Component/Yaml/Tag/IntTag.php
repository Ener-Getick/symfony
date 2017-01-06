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

final class IntTag implements ImplicitTagInterface
{
    /**
     * {@inheritdoc}
     */
    public function construct($value, $implicit = false)
    {
        if (!is_string($value) || (!$implicit && !$this->recognize($value))) {
            throw new \LogicException('not an int');
        }

        if (isset($value[1])) {
            // Hexadecimal
            if ('x' === $value[1]) {
                return hexdec($value);
            }
            // Octal
            if ('o' === $value[1]) {
                return octdec(substr($value, 2));
            }
            if ('0' === $value[0] || ($value[0] === '-' && $value[1] === '0')) {
                return octdec($value);
            }
        }

        $value = str_replace('_', '', $value);

        $raw = $value;
        $cast = (int) $value;

        return ((string) $raw === (string) $cast) ? $cast : $raw;
    }

    /**
     * {@inheritdoc}
     */
    public function recognize($value)
    {
        if ('' === $value) {
            return false;
        }

        if ('0' === $value[0] && isset($value[1])) {
            // Octal
            if ('o' === $value[1] && (strlen($value) - 2) === strspn($value, '01234567_', 2)) {
                return true;
            }
            // Hexadecimal
            if ('x' === $value[1] && (strlen($value) - 2) === strspn($value, '0123456789ABCDEF_', 2)) {
                return true;
            }
        }

        // +/- is not supported by ctype_digit
        if ('+' === $value[0] || '-' === $value[0]) {
            $value = substr($value, 1);
        }
        if (!is_numeric($value[0])) {
            return false;
        }

        $value = str_replace('_', '', $value);

        return ctype_digit($value);
    }
}
