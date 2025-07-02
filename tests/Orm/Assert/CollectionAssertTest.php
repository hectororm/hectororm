<?php

namespace Hector\Orm\Tests\Assert;

use Hector\Orm\Assert\CollectionAssert;
use Hector\Orm\Collection\Collection;
use Hector\Orm\Tests\Fake\Entity\LanguageCollection;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use stdClass;
use TypeError;

class CollectionAssertTest extends TestCase
{
    public function testAssertCollection()
    {
        $this->expectNotToPerformAssertions();

        $trait = $this->getMockForTrait(CollectionAssert::class);

        $rMethod = new ReflectionMethod($trait, 'assertCollection');
        $rMethod->setAccessible(true);

        $rMethod->invoke($trait, Collection::class);
        $rMethod->invoke($trait, new Collection());
    }

    public function testAssertCollection_failedWithObject()
    {
        $this->expectException(TypeError::class);

        $trait = $this->getMockForTrait(CollectionAssert::class);

        $rMethod = new ReflectionMethod($trait, 'assertCollection');
        $rMethod->setAccessible(true);

        $rMethod->invoke($trait, new stdClass());
    }

    public function testAssertCollection_failedWithString()
    {
        $this->expectException(TypeError::class);

        $trait = $this->getMockForTrait(CollectionAssert::class);

        $rMethod = new ReflectionMethod($trait, 'assertCollection');
        $rMethod->setAccessible(true);

        $rMethod->invoke($trait, stdClass::class);
    }
}
