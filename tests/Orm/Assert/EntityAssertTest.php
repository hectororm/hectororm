<?php

namespace Hector\Orm\Tests\Assert;

use Hector\Orm\Assert\EntityAssert;
use Hector\Orm\Tests\Fake\Entity\Actor;
use Hector\Orm\Tests\Fake\Entity\Film;
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

    public function testAssertEntityType()
    {
        $this->expectNotToPerformAssertions();

        $trait = $this->getMockForTrait(EntityAssert::class);

        $rMethod = new ReflectionMethod($trait, 'assertEntityType');
        $rMethod->setAccessible(true);

        $rMethod->invoke($trait, Actor::class, Actor::class);
        $rMethod->invoke($trait, new Actor(), Actor::class);
    }

    public function testAssertEntityType_failedWithObject()
    {
        $this->expectException(TypeError::class);

        $trait = $this->getMockForTrait(EntityAssert::class);

        $rMethod = new ReflectionMethod($trait, 'assertEntityType');
        $rMethod->setAccessible(true);

        $rMethod->invoke($trait, new Film(), Actor::class);
    }

    public function testAssertEntityType_failedWithString()
    {
        $this->expectException(TypeError::class);

        $trait = $this->getMockForTrait(EntityAssert::class);

        $rMethod = new ReflectionMethod($trait, 'assertEntityType');
        $rMethod->setAccessible(true);

        $rMethod->invoke($trait, Film::class, Actor::class);
    }
}
