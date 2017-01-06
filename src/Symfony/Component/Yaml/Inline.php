<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Yaml;

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Exception\DumpException;
use Symfony\Component\Yaml\Tag\TagResolver;
use Symfony\Component\Yaml\Tag\TimestampTag;

/**
 * Inline implements a YAML parser/dumper for the YAML inline syntax.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @internal
 */
class Inline
{
    const REGEX_QUOTED_STRING = '(?:"([^"\\\\]*(?:\\\\.[^"\\\\]*)*)"|\'([^\']*(?:\'\'[^\']*)*)\')';

    public static $parsedLineNumber;
    public static $tagResolver;

    private static $exceptionOnInvalidType = false;
    private static $objectSupport = false;
    private static $objectForMap = false;
    private static $constantSupport = false;

    /**
     * Converts a YAML string to a PHP value.
     *
     * @param string $value      A YAML string
     * @param int    $flags      A bit field of PARSE_* constants to customize the YAML parser behavior
     * @param array  $references Mapping of variable names to values
     *
     * @return mixed A PHP value
     *
     * @throws ParseException
     */
    public static function parse($value, $flags = 0, $references = array())
    {
        if (is_bool($flags)) {
            @trigger_error('Passing a boolean flag to toggle exception handling is deprecated since version 3.1 and will be removed in 4.0. Use the Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE flag instead.', E_USER_DEPRECATED);

            if ($flags) {
                $flags = Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE;
            } else {
                $flags = 0;
            }
        }

        if (func_num_args() >= 3 && !is_array($references)) {
            @trigger_error('Passing a boolean flag to toggle object support is deprecated since version 3.1 and will be removed in 4.0. Use the Yaml::PARSE_OBJECT flag instead.', E_USER_DEPRECATED);

            if ($references) {
                $flags |= Yaml::PARSE_OBJECT;
            }

            if (func_num_args() >= 4) {
                @trigger_error('Passing a boolean flag to toggle object for map support is deprecated since version 3.1 and will be removed in 4.0. Use the Yaml::PARSE_OBJECT_FOR_MAP flag instead.', E_USER_DEPRECATED);

                if (func_get_arg(3)) {
                    $flags |= Yaml::PARSE_OBJECT_FOR_MAP;
                }
            }

            if (func_num_args() >= 5) {
                $references = func_get_arg(4);
            } else {
                $references = array();
            }
        }

        self::$exceptionOnInvalidType = (bool) (Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE & $flags);
        self::$objectSupport = (bool) (Yaml::PARSE_OBJECT & $flags);
        self::$objectForMap = (bool) (Yaml::PARSE_OBJECT_FOR_MAP & $flags);
        self::$constantSupport = (bool) (Yaml::PARSE_CONSTANT & $flags);

        $value = trim($value);

        if ('' === $value) {
            return '';
        }

        if (2 /* MB_OVERLOAD_STRING */ & (int) ini_get('mbstring.func_overload')) {
            $mbEncoding = mb_internal_encoding();
            mb_internal_encoding('ASCII');
        }

        $i = 0;
        switch ($value[0]) {
            case '[':
                $result = self::parseSequence($value, $flags, $i, $references);
                ++$i;
                break;
            case '{':
                $result = self::parseMapping($value, $flags, $i, $references);
                ++$i;
                break;
            default:
                $result = self::parseScalar($value, $flags, null, array('"', "'"), $i, true, $references);
        }

        // some comments are allowed at the end
        if (preg_replace('/\s+#.*$/A', '', substr($value, $i))) {
            throw new ParseException(sprintf('Unexpected characters near "%s".', substr($value, $i)));
        }

        if (isset($mbEncoding)) {
            mb_internal_encoding($mbEncoding);
        }

        return $result;
    }

    /**
     * Dumps a given PHP variable to a YAML string.
     *
     * @param mixed $value The PHP variable to convert
     * @param int   $flags A bit field of Yaml::DUMP_* constants to customize the dumped YAML string
     *
     * @return string The YAML string representing the PHP value
     *
     * @throws DumpException When trying to dump PHP resource
     */
    public static function dump($value, $flags = 0)
    {
        if (is_bool($flags)) {
            @trigger_error('Passing a boolean flag to toggle exception handling is deprecated since version 3.1 and will be removed in 4.0. Use the Yaml::DUMP_EXCEPTION_ON_INVALID_TYPE flag instead.', E_USER_DEPRECATED);

            if ($flags) {
                $flags = Yaml::DUMP_EXCEPTION_ON_INVALID_TYPE;
            } else {
                $flags = 0;
            }
        }

        if (func_num_args() >= 3) {
            @trigger_error('Passing a boolean flag to toggle object support is deprecated since version 3.1 and will be removed in 4.0. Use the Yaml::DUMP_OBJECT flag instead.', E_USER_DEPRECATED);

            if (func_get_arg(2)) {
                $flags |= Yaml::DUMP_OBJECT;
            }
        }

        switch (true) {
            case is_resource($value):
                if (Yaml::DUMP_EXCEPTION_ON_INVALID_TYPE & $flags) {
                    throw new DumpException(sprintf('Unable to dump PHP resources in a YAML file ("%s").', get_resource_type($value)));
                }

                return 'null';
            case $value instanceof \DateTimeInterface:
                return $value->format('c');
            case is_object($value):
                if (Yaml::DUMP_OBJECT & $flags) {
                    return '!php/object:'.serialize($value);
                }

                if (Yaml::DUMP_OBJECT_AS_MAP & $flags && ($value instanceof \stdClass || $value instanceof \ArrayObject)) {
                    return self::dumpArray((array) $value, $flags);
                }

                if (Yaml::DUMP_EXCEPTION_ON_INVALID_TYPE & $flags) {
                    throw new DumpException('Object support when dumping a YAML file has been disabled.');
                }

                return 'null';
            case is_array($value):
                return self::dumpArray($value, $flags);
            case null === $value:
                return 'null';
            case true === $value:
                return 'true';
            case false === $value:
                return 'false';
            case ctype_digit($value):
                return is_string($value) ? "'$value'" : (int) $value;
            case is_numeric($value):
                $locale = setlocale(LC_NUMERIC, 0);
                if (false !== $locale) {
                    setlocale(LC_NUMERIC, 'C');
                }
                if (is_float($value)) {
                    $repr = (string) $value;
                    if (is_infinite($value)) {
                        $repr = str_ireplace('INF', '.Inf', $repr);
                    } elseif (floor($value) == $value && $repr == $value) {
                        // Preserve float data type since storing a whole number will result in integer value.
                        $repr = '!!float '.$repr;
                    }
                } else {
                    $repr = is_string($value) ? "'$value'" : (string) $value;
                }
                if (false !== $locale) {
                    setlocale(LC_NUMERIC, $locale);
                }

                return $repr;
            case '' == $value:
                return "''";
            case self::isBinaryString($value):
                return '!!binary '.base64_encode($value);
            case Escaper::requiresDoubleQuoting($value):
                return Escaper::escapeWithDoubleQuotes($value);
            case Escaper::requiresSingleQuoting($value):
            case preg_match('{^[0-9]+[_0-9]*$}', $value):
            case preg_match(self::getHexRegex(), $value):
            case preg_match(TimestampTag::TIMESTAMP_REGEX, $value):
                return Escaper::escapeWithSingleQuotes($value);
            default:
                return $value;
        }
    }

    /**
     * Check if given array is hash or just normal indexed array.
     *
     * @internal
     *
     * @param array $value The PHP array to check
     *
     * @return bool true if value is hash array, false otherwise
     */
    public static function isHash(array $value)
    {
        $expectedKey = 0;

        foreach ($value as $key => $val) {
            if ($key !== $expectedKey++) {
                return true;
            }
        }

        return false;
    }

    /**
     * Dumps a PHP array to a YAML string.
     *
     * @param array $value The PHP array to dump
     * @param int   $flags A bit field of Yaml::DUMP_* constants to customize the dumped YAML string
     *
     * @return string The YAML string representing the PHP array
     */
    private static function dumpArray($value, $flags)
    {
        // array
        if ($value && !self::isHash($value)) {
            $output = array();
            foreach ($value as $val) {
                $output[] = self::dump($val, $flags);
            }

            return sprintf('[%s]', implode(', ', $output));
        }

        // hash
        $output = array();
        foreach ($value as $key => $val) {
            $output[] = sprintf('%s: %s', self::dump($key, $flags), self::dump($val, $flags));
        }

        return sprintf('{ %s }', implode(', ', $output));
    }

    /**
     * Parses a YAML scalar.
     *
     * @param string $scalar
     * @param int    $flags
     * @param string $delimiters
     * @param array  $stringDelimiters
     * @param int    &$i
     * @param bool   $evaluate
     * @param array  $references
     *
     * @return string
     *
     * @throws ParseException When malformed inline YAML string is parsed
     *
     * @internal
     */
    public static function parseScalar($scalar, $flags = 0, $delimiters = null, $stringDelimiters = array('"', "'"), &$i = 0, $evaluate = true, $references = array())
    {
        if (in_array($scalar[$i], $stringDelimiters)) {
            // quoted scalar
            $output = self::parseQuotedScalar($scalar, $i);

            if (null !== $delimiters) {
                $tmp = ltrim(substr($scalar, $i), ' ');
                if (!in_array($tmp[0], $delimiters)) {
                    throw new ParseException(sprintf('Unexpected characters (%s).', substr($scalar, $i)));
                }
            }
        } else {
            // "normal" string
            if (!$delimiters) {
                $output = substr($scalar, $i);
                $i += strlen($output);

                // remove comments
                if (preg_match('/[ \t]+#/', $output, $match, PREG_OFFSET_CAPTURE)) {
                    $output = substr($output, 0, $match[0][1]);
                }
            } elseif (preg_match('/^(.+?)('.implode('|', $delimiters).')/', substr($scalar, $i), $match)) {
                $output = $match[1];
                $i += strlen($output);
            } else {
                throw new ParseException(sprintf('Malformed inline YAML string: %s.', $scalar));
            }

            // a non-quoted string cannot start with @ or ` (reserved) nor with a scalar indicator (| or >)
            if ($output && ('@' === $output[0] || '`' === $output[0] || '|' === $output[0] || '>' === $output[0])) {
                throw new ParseException(sprintf('The reserved indicator "%s" cannot start a plain scalar; you need to quote the scalar.', $output[0]));
            }

            if ($output && '%' === $output[0]) {
                @trigger_error(sprintf('Not quoting the scalar "%s" starting with the "%%" indicator character is deprecated since Symfony 3.1 and will throw a ParseException in 4.0.', $output), E_USER_DEPRECATED);
            }

            $output = trim($output);
            if ($evaluate) {
                $output = self::evaluateScalar($output, $flags, $references);
            }
        }

        return $output;
    }

    /**
     * Parses a YAML quoted scalar.
     *
     * @param string $scalar
     * @param int    &$i
     *
     * @return string
     *
     * @throws ParseException When malformed inline YAML string is parsed
     */
    private static function parseQuotedScalar($scalar, &$i)
    {
        if (!preg_match('/'.self::REGEX_QUOTED_STRING.'/Au', substr($scalar, $i), $match)) {
            throw new ParseException(sprintf('Malformed inline YAML string: %s.', substr($scalar, $i)));
        }

        $output = substr($match[0], 1, strlen($match[0]) - 2);

        $unescaper = new Unescaper();
        if ('"' == $scalar[$i]) {
            $output = $unescaper->unescapeDoubleQuotedString($output);
        } else {
            $output = $unescaper->unescapeSingleQuotedString($output);
        }

        $i += strlen($match[0]);

        return $output;
    }

    /**
     * Parses a YAML sequence.
     *
     * @param string $sequence
     * @param int    $flags
     * @param int    &$i
     * @param array  $references
     *
     * @return array
     *
     * @throws ParseException When malformed inline YAML string is parsed
     */
    private static function parseSequence($sequence, $flags, &$i = 0, $references = array())
    {
        $output = array();
        $len = strlen($sequence);
        ++$i;

        // [foo, bar, ...]
        while ($i < $len) {
            switch ($sequence[$i]) {
                case '[':
                    // nested sequence
                    $output[] = self::parseSequence($sequence, $flags, $i, $references);
                    break;
                case '{':
                    // nested mapping
                    $output[] = self::parseMapping($sequence, $flags, $i, $references);
                    break;
                case ']':
                    return $output;
                case ',':
                case ' ':
                    break;
                default:
                    $isQuoted = in_array($sequence[$i], array('"', "'"));
                    $value = self::parseScalar($sequence, $flags, array(',', ']'), array('"', "'"), $i, true, $references);

                    // the value can be an array if a reference has been resolved to an array var
                    if (is_string($value) && !$isQuoted && false !== strpos($value, ': ')) {
                        // embedded mapping?
                        try {
                            $pos = 0;
                            $value = self::parseMapping('{'.$value.'}', $flags, $pos, $references);
                        } catch (\InvalidArgumentException $e) {
                            // no, it's not
                        }
                    }

                    $output[] = $value;

                    --$i;
            }

            ++$i;
        }

        throw new ParseException(sprintf('Malformed inline YAML string: %s.', $sequence));
    }

    /**
     * Parses a YAML mapping.
     *
     * @param string $mapping
     * @param int    $flags
     * @param int    &$i
     * @param array  $references
     *
     * @return array|\stdClass
     *
     * @throws ParseException When malformed inline YAML string is parsed
     */
    private static function parseMapping($mapping, $flags, &$i = 0, $references = array())
    {
        $output = array();
        $len = strlen($mapping);
        ++$i;

        // {foo: bar, bar:foo, ...}
        while ($i < $len) {
            switch ($mapping[$i]) {
                case ' ':
                case ',':
                    ++$i;
                    continue 2;
                case '}':
                    if (self::$objectForMap) {
                        return (object) $output;
                    }

                    return $output;
            }

            // key
            $key = self::parseScalar($mapping, $flags, array(':', ' '), array('"', "'"), $i, false);

            if (false === $i = strpos($mapping, ':', $i)) {
                break;
            }

            if (!isset($mapping[$i + 1]) || !in_array($mapping[$i + 1], array(' ', '[', ']', '{', '}'), true)) {
                @trigger_error('Using a colon that is not followed by an indication character (i.e. " ", ",", "[", "]", "{", "}" is deprecated since version 3.2 and will throw a ParseException in 4.0.', E_USER_DEPRECATED);
            }

            // value
            $done = false;

            while ($i < $len) {
                switch ($mapping[$i]) {
                    case '[':
                        // nested sequence
                        $value = self::parseSequence($mapping, $flags, $i, $references);
                        // Spec: Keys MUST be unique; first one wins.
                        // Parser cannot abort this mapping earlier, since lines
                        // are processed sequentially.
                        if (!isset($output[$key])) {
                            $output[$key] = $value;
                        } else {
                            @trigger_error(sprintf('Duplicate key "%s" detected on line %d whilst parsing YAML. Silent handling of duplicate mapping keys in YAML is deprecated since version 3.2 and will throw \Symfony\Component\Yaml\Exception\ParseException in 4.0.', $key, self::$parsedLineNumber + 1), E_USER_DEPRECATED);
                        }
                        $done = true;
                        break;
                    case '{':
                        // nested mapping
                        $value = self::parseMapping($mapping, $flags, $i, $references);
                        // Spec: Keys MUST be unique; first one wins.
                        // Parser cannot abort this mapping earlier, since lines
                        // are processed sequentially.
                        if (!isset($output[$key])) {
                            $output[$key] = $value;
                        } else {
                            @trigger_error(sprintf('Duplicate key "%s" detected on line %d whilst parsing YAML. Silent handling of duplicate mapping keys in YAML is deprecated since version 3.2 and will throw \Symfony\Component\Yaml\Exception\ParseException in 4.0.', $key, self::$parsedLineNumber + 1), E_USER_DEPRECATED);
                        }
                        $done = true;
                        break;
                    case ':':
                    case ' ':
                        break;
                    default:
                        $value = self::parseScalar($mapping, $flags, array(',', '}'), array('"', "'"), $i, true, $references);
                        // Spec: Keys MUST be unique; first one wins.
                        // Parser cannot abort this mapping earlier, since lines
                        // are processed sequentially.
                        if (!isset($output[$key])) {
                            $output[$key] = $value;
                        } else {
                            @trigger_error(sprintf('Duplicate key "%s" detected on line %d whilst parsing YAML. Silent handling of duplicate mapping keys in YAML is deprecated since version 3.2 and will throw \Symfony\Component\Yaml\Exception\ParseException in 4.0.', $key, self::$parsedLineNumber + 1), E_USER_DEPRECATED);
                        }
                        $done = true;
                        --$i;
                }

                ++$i;

                if ($done) {
                    continue 2;
                }
            }
        }

        throw new ParseException(sprintf('Malformed inline YAML string: %s.', $mapping));
    }

    /**
     * Evaluates scalars and replaces magic values.
     *
     * @param string $scalar
     * @param int    $flags
     * @param array  $references
     *
     * @return string A YAML string
     *
     * @throws ParseException when object parsing support was disabled and the parser detected a PHP object or when a reference could not be resolved
     */
    private static function evaluateScalar($scalar, $flags, $references = array())
    {
        if (0 === strpos($scalar, '*')) {
            if (false !== $pos = strpos($scalar, '#')) {
                $value = substr($scalar, 1, $pos - 2);
            } else {
                $value = substr($scalar, 1);
            }

            // an unquoted *
            if (false === $value || '' === $value) {
                throw new ParseException('A reference must contain at least one character.');
            }

            if (!array_key_exists($value, $references)) {
                throw new ParseException(sprintf('Reference "%s" does not exist.', $value));
            }

            return $references[$value];
        }

        $tagResolver = self::getTagResolver($flags);
        if ($scalar && '!' === $scalar[0]) {
            if (0 === strpos($scalar, '!php/object:')) {
                if (self::$objectSupport) {
                    return unserialize(substr($scalar, 12));
                }

                if (self::$exceptionOnInvalidType) {
                    throw new ParseException('Object support when parsing a YAML file has been disabled.');
                }

                return;
            }
            if (0 === strpos($scalar, '!!php/object:')) {
                if (self::$objectSupport) {
                    @trigger_error('The !!php/object tag to indicate dumped PHP objects is deprecated since version 3.1 and will be removed in 4.0. Use the !php/object tag instead.', E_USER_DEPRECATED);

                    return unserialize(substr($scalar, 13));
                }

                if (self::$exceptionOnInvalidType) {
                    throw new ParseException('Object support when parsing a YAML file has been disabled.');
                }

                return;
            }
            if (0 === strpos($scalar, '!php/const:')) {
                if (self::$constantSupport) {
                    if (defined($const = substr($scalar, 11))) {
                        return constant($const);
                    }

                    throw new ParseException(sprintf('The constant "%s" is not defined.', $const));
                }
                if (self::$exceptionOnInvalidType) {
                    throw new ParseException(sprintf('The string "%s" could not be parsed as a constant. Have you forgotten to pass the "Yaml::PARSE_CONSTANT" flag to the parser?', $scalar));
                }

                return;
            }

            $tagLength = strcspn($scalar, " \t", 1);
            $tag = substr($scalar, 1, $tagLength);
            if ($tagResolver->supportsTag($tag)) {
                $i = 0;
                $scalar = self::parseScalar(ltrim(substr($scalar, $tagLength + 1)), $flags, null, array('"', "'"), $i, false, $references);

                return $tagResolver->resolve($scalar, $tag);
            }
        }

        return $tagResolver->resolve($scalar);
    }

    private static function isBinaryString($value)
    {
        return !preg_match('//u', $value) || preg_match('/[^\x09-\x0d\x20-\xff]/', $value);
    }

    /**
     * Gets a regex that matches a YAML number in hexadecimal notation.
     *
     * @return string
     */
    private static function getHexRegex()
    {
        return '~^0x[0-9a-f_]++$~i';
    }

    private static function getTagResolver($flags)
    {
        if (null === self::$tagResolver) {
            return TagResolver::create($flags);
        }

        return self::$tagResolver;
    }
}
