<?php

namespace Hector\Query\Tests\Component;

use Hector\Query\Component\EncapsulateHelperTrait;
use PHPUnit\Framework\TestCase;

class EncapsulateHelperTraitTest extends TestCase
{
    public function testEncapsulate()
    {
        /** @var EncapsulateHelperTrait $trait */
        $trait = $this->getMockForTrait(EncapsulateHelperTrait::class);

        $this->assertEquals('( STR )', $trait->encapsulate('STR'));
    }

    public function testEncapsulate_false()
    {
        /** @var EncapsulateHelperTrait $trait */
        $trait = $this->getMockForTrait(EncapsulateHelperTrait::class);

        $this->assertEquals('STR', $trait->encapsulate('STR', false));
    }

    public function testEncapsulate_emptyStr()
    {
        /** @var EncapsulateHelperTrait $trait */
        $trait = $this->getMockForTrait(EncapsulateHelperTrait::class);

        $this->assertNull($trait->encapsulate(''));
    }

    public function testEncapsulate_nullStr()
    {
        /** @var EncapsulateHelperTrait $trait */
        $trait = $this->getMockForTrait(EncapsulateHelperTrait::class);

        $this->assertNull($trait->encapsulate(null));
    }
}
