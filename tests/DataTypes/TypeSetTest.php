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

use Hector\DataTypes\Type\StringType;
use Hector\DataTypes\Type;
use Hector\DataTypes\TypeSet;
use PHPUnit\Framework\TestCase;

class TypeSetTest extends TestCase
{
    public function testReset()
    {
        $typeSet = new TypeSet();
        $initialCount = count($typeSet);
        $typeSet->add('fake', new StringType());

        $this->assertCount($initialCount + 1, $typeSet);

        $typeSet->reset();

        $this->assertCount($initialCount, $typeSet);
    }

    public function testAdd()
    {
        $typeSet = new TypeSet();
        $typeSet->add('fake', $typeObj = new StringType());

        $this->assertSame($typeObj, $typeSet->get('fake'));
    }

    public function testGet()
    {
        $typeSet = new TypeSet();

        $this->assertInstanceOf(Type\StringType::class, $typeSet->get('char'));
        $this->assertInstanceOf(Type\StringType::class, $typeSet->get('varchar'));
        $this->assertInstanceOf(Type\StringType::class, $typeSet->get('tinytext'));
        $this->assertInstanceOf(Type\StringType::class, $typeSet->get('text'));
        $this->assertInstanceOf(Type\StringType::class, $typeSet->get('mediumtext'));
        $this->assertInstanceOf(Type\StringType::class, $typeSet->get('longtext'));
        $this->assertInstanceOf(Type\StringType::class, $typeSet->get('tinyblob'));
        $this->assertInstanceOf(Type\StringType::class, $typeSet->get('blob'));
        $this->assertInstanceOf(Type\StringType::class, $typeSet->get('mediumblob'));
        $this->assertInstanceOf(Type\StringType::class, $typeSet->get('longblog'));
        $this->assertInstanceOf(Type\NumericType::class, $typeSet->get('tinyint'));
        $this->assertInstanceOf(Type\NumericType::class, $typeSet->get('smallint'));
        $this->assertInstanceOf(Type\NumericType::class, $typeSet->get('mediumint'));
        $this->assertInstanceOf(Type\NumericType::class, $typeSet->get('int'));
        $this->assertInstanceOf(Type\NumericType::class, $typeSet->get('bigint'));
        $this->assertInstanceOf(Type\NumericType::class, $typeSet->get('decimal'));
        $this->assertInstanceOf(Type\NumericType::class, $typeSet->get('numeric'));
        $this->assertInstanceOf(Type\NumericType::class, $typeSet->get('float'));
        $this->assertInstanceOf(Type\NumericType::class, $typeSet->get('double'));
        $this->assertInstanceOf(Type\DateTimeType::class, $typeSet->get('date'));
        $this->assertInstanceOf(Type\DateTimeType::class, $typeSet->get('datetime'));
        $this->assertInstanceOf(Type\DateTimeType::class, $typeSet->get('timestamp'));
        $this->assertInstanceOf(Type\NumericType::class, $typeSet->get('year'));
        $this->assertInstanceOf(Type\StringType::class, $typeSet->get('enum'));
        $this->assertInstanceOf(Type\SetType::class, $typeSet->get('set'));
        $this->assertInstanceOf(Type\JsonType::class, $typeSet->get('json'));
    }
}
