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

final class TimestampTag implements ImplicitTagInterface
{
    /**
     * @internal
     */
    const TIMESTAMP_REGEX = <<<EOF
        ~^
        (?P<year>[0-9][0-9][0-9][0-9])
        -(?P<month>[0-9][0-9]?)
        -(?P<day>[0-9][0-9]?)
        (?:(?:[Tt]|[ \t]+)
        (?P<hour>[0-9][0-9]?)
        :(?P<minute>[0-9][0-9])
        :(?P<second>[0-9][0-9])
        (?:\.(?P<fraction>[0-9]*))?
        (?:[ \t]*(?P<tz>Z|(?P<tz_sign>[-+])(?P<tz_hour>[0-9][0-9]?)
        (?::(?P<tz_minute>[0-9][0-9]))?))?)?
        $~x
EOF;

    private $useDatetime;

    /**
     * @param bool $useDatetime
     */
    public function __construct($useDatetime = true) {
        $this->useDatetime = $useDatetime;
    }

    /**
     * {@inheritdoc}
     */
    public function construct($value, $implicit = false)
    {
        if (!is_string($value) || (!$implicit && !$this->recognize($value))) {
            throw new \LogicException('not a timestamp');
        }

        if ($this->useDatetime) {
            // When no timezone is provided in the parsed date, YAML spec says we must assume UTC.
            return new \DateTime($value, new \DateTimeZone('UTC'));
        }

        $timeZone = date_default_timezone_get();
        date_default_timezone_set('UTC');
        $time = strtotime($value);
        date_default_timezone_set($timeZone);

        return $time;
    }

    /**
     * {@inheritdoc}
     */
    public function recognize($value)
    {
        // Minimal length of 8
        if (!isset($value[7]) || !ctype_digit(substr($value, 0, 4))) {
            return false;
        }

        return preg_match(self::TIMESTAMP_REGEX, $value);
    }
}
