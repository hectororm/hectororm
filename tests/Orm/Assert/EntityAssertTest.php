<?php

namespace Hector\Orm\Tests\Assert;

use Hector\Orm\Assert\EntityAssert;
use Hector\Orm\Tests\Fake\Entity\Actor;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use stdClass;
use TypeError;

class EntityAssertTest extends TestCase
{
    public function testAssertEntity()
    {
        $this->expectNotToPerformAssertions();

        $trait = $this->getMockForTrait(EntityAssert::class);

        $rMethod = new ReflectionMethod($trait, 'assertEntity');
        $rMethod->setAccessible(true);

        $rMethod->invoke($trait, Actor::class);
        $rMethod->invoke($trait, new Actor());
    }

    public function testAssertEntity_failedWithObject()
    {
        $this->expectException(TypeError::class);

        $trait = $this->getMockForTrait(EntityAssert::class);

        $rMethod = new ReflectionMethod($trait, 'assertEntity');
        $rMethod->setAccessible(true);

        $rMethod->invoke($trait, new stdClass());
    }

    public function testAssertEntity_failedWithString()
    {
        $this->expectException(TypeError::class);

        $trait = $this->getMockForTrait(EntityAssert::class);

        $rMethod = new ReflectionMethod($trait, 'assertEntity');
        $rMethod->setAccessible(true);

        $rMethod->invoke($trait, stdClass::class);
    }
}
