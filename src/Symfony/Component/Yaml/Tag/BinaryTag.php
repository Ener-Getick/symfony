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

use Symfony\Component\Yaml\Exception\ParseException;

final class BinaryTag implements TagInterface
{
    /**
     * {@inheritdoc}
     */
    public function construct($value)
    {
        if (!is_string($value)) {
            throw new \LogicException(sprintf('Expected binary of type "string", got ""', gettype($value)));
        }

        $value = preg_replace('/\s/', '', $value);

        if (0 !== (strlen($value) % 4)) {
            throw new ParseException(sprintf('The normalized base64 encoded data (data without whitespace characters) length must be a multiple of four (%d bytes given).', strlen($value)));
        }

        if (!preg_match('#^[A-Z0-9+/]+={0,2}$#i', $value)) {
            throw new ParseException(sprintf('The base64 encoded data (%s) contains invalid characters.', $value));
        }

        return base64_decode($value, true);
    }
}
