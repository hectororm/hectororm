<?php
/*
 * This file is part of Hector ORM.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2021 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

namespace Hector\DataTypes\Tests;

use Hector\DataTypes\Type\NumericType;
use Hector\DataTypes\Type\DateTimeType;
use Hector\DataTypes\Type\SetType;
use Hector\DataTypes\Type\JsonType;
use Hector\DataTypes\Type\StringType;
use Hector\DataTypes\TypeSet;
use PHPUnit\Framework\TestCase;

class TypeSetTest extends TestCase
{
    public function testConstruct(): void
    {
        $typeSet = new TypeSet(['fake' => $typeObj = new StringType()]);

        $this->assertSame($typeObj, $typeSet->get('fake'));
    }

    public function testReset(): void
    {
        $typeSet = new TypeSet();
        $initialCount = count($typeSet);
        $typeSet->add('fake', new StringType());

        $this->assertCount($initialCount + 1, $typeSet);

        $typeSet->reset();

        $this->assertCount($initialCount, $typeSet);
    }

    public function testAdd(): void
    {
        $typeSet = new TypeSet();
        $typeSet->add('fake', $typeObj = new StringType());

        $this->assertSame($typeObj, $typeSet->get('fake'));
    }

    public function testGet(): void
    {
        $typeSet = new TypeSet();

        $this->assertInstanceOf(StringType::class, $typeSet->get('char'));
        $this->assertInstanceOf(StringType::class, $typeSet->get('varchar'));
        $this->assertInstanceOf(StringType::class, $typeSet->get('tinytext'));
        $this->assertInstanceOf(StringType::class, $typeSet->get('text'));
        $this->assertInstanceOf(StringType::class, $typeSet->get('mediumtext'));
        $this->assertInstanceOf(StringType::class, $typeSet->get('longtext'));
        $this->assertInstanceOf(StringType::class, $typeSet->get('tinyblob'));
        $this->assertInstanceOf(StringType::class, $typeSet->get('blob'));
        $this->assertInstanceOf(StringType::class, $typeSet->get('mediumblob'));
        $this->assertInstanceOf(StringType::class, $typeSet->get('longblog'));
        $this->assertInstanceOf(NumericType::class, $typeSet->get('tinyint'));
        $this->assertInstanceOf(NumericType::class, $typeSet->get('smallint'));
        $this->assertInstanceOf(NumericType::class, $typeSet->get('mediumint'));
        $this->assertInstanceOf(NumericType::class, $typeSet->get('int'));
        $this->assertInstanceOf(NumericType::class, $typeSet->get('bigint'));
        $this->assertInstanceOf(NumericType::class, $typeSet->get('decimal'));
        $this->assertInstanceOf(NumericType::class, $typeSet->get('numeric'));
        $this->assertInstanceOf(NumericType::class, $typeSet->get('float'));
        $this->assertInstanceOf(NumericType::class, $typeSet->get('double'));
        $this->assertInstanceOf(DateTimeType::class, $typeSet->get('date'));
        $this->assertInstanceOf(DateTimeType::class, $typeSet->get('datetime'));
        $this->assertInstanceOf(DateTimeType::class, $typeSet->get('timestamp'));
        $this->assertInstanceOf(NumericType::class, $typeSet->get('year'));
        $this->assertInstanceOf(StringType::class, $typeSet->get('enum'));
        $this->assertInstanceOf(SetType::class, $typeSet->get('set'));
        $this->assertInstanceOf(JsonType::class, $typeSet->get('json'));
    }
}
