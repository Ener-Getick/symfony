<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Dumper;

use Doctrine\Common\Annotations\AnnotationReader;
use Symfony\Component\Serializer\Mapping\Loader\AnnotationLoader;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Tests\Normalizer\ObjectNormalizerTest;
use Symfony\Component\Serializer\Dumper\NormalizerDumper;

class NormalizerDumperTest extends ObjectNormalizerTest
{
    protected function getNormalizerFor($class)
    {
        $normalizerName = 'Test'.md5($class).'Normalizer';

        if (!class_exists($normalizerName)) {
            $classMetadataFactory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()));
            $dumper = new NormalizerDumper($classMetadataFactory);

            eval('?>'.$dumper->dump($class, array('class' => $normalizerName)));
        }

        $normalizer = new $normalizerName();
        $normalizer->setNormalizer($this->serializer);

        return $normalizer;
    }
}
