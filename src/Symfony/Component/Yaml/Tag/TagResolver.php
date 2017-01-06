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

use Symfony\Component\Yaml\Yaml;

final class TagResolver
{
    private $tags;
    private $implicitTags;

    /**
     * @param TagInterface[] $tags with the associated tag as key
     */
    public function __construct(array $tags = array())
    {
        $this->tags = $tags;
        foreach ($tags as $tag) {
            if ($tag instanceof ImplicitTagInterface) {
                $this->implicitTags[] = $tag;
            }
        }
    }

    public function resolve($value, $tag = null)
    {

        if (null === $tag) {
            foreach ($this->implicitTags as $implicitTag) {
                if ($implicitTag->recognize($value)) {
                    return $implicitTag->construct($value, true);
                }
            }

            throw new \LogicException(sprintf('Unsupported plain scalar "%s".', $value));
        }

        $tag = $this->prefixTag($tag);
        if (isset($this->tags[$tag])) {
            return $this->tags[$tag]->construct($value, false);
        }
    }

    /**
     * @internal
     */
    public function supportsTag($tag)
    {
        $tag = $this->prefixTag($tag);

        return isset($this->tags[$tag]);
    }

    /**
     * @internal
     */
    public static function create($flags)
    {
        return new self(array(
            '' => new NonSpecificTag(),
            'tag:yaml.org,2002:null' => new NullTag(),
            'tag:yaml.org,2002:bool' => new BoolTag(),
            'tag:yaml.org,2002:int' => new IntTag(),
            'tag:yaml.org,2002:float' => new FloatTag(),
            'tag:yaml.org,2002:timestamp' => new TimestampTag((bool) (Yaml::PARSE_DATETIME & $flags)),
            'tag:yaml.org,2002:binary' => new BinaryTag(),
            'tag:yaml.org,2002:str' => $strTag = new StrTag(),
            'str' => $strTag, // Local alias
        ));
    }

    private function prefixTag($tag)
    {
        if ($tag && '!' === $tag[0]) {
            $tag = 'tag:yaml.org,2002:'.substr($tag, 1);
        }

        return $tag;
    }
}
