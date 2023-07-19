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

namespace Hector\Schema\Tests;

use Hector\Schema\Column;

class ColumnTest extends AbstractTestCase
{
    public function testSerialization()
    {
        $table = $this->getSchemaContainer()->getSchema('sakila')->getTable('customer');
        $column = $table->getColumn('first_name');
        /** @var Column $column2 */
        $column2 = unserialize(serialize($column));

        $this->assertEquals($column->getName(), $column2->getName());
        $this->assertEquals($column->getPosition(), $column2->getPosition());
        $this->assertEquals($column->getDefault(), $column2->getDefault());
        $this->assertEquals($column->isNullable(), $column2->isNullable());
        $this->assertEquals($column->getType(), $column2->getType());
        $this->assertEquals($column->isAutoIncrement(), $column2->isAutoIncrement());
        $this->assertEquals($column->getMaxlength(), $column2->getMaxlength());
        $this->assertEquals($column->getNumericPrecision(), $column2->getNumericPrecision());
        $this->assertEquals($column->getNumericScale(), $column2->getNumericScale());
        $this->assertEquals($column->isUnsigned(), $column2->isUnsigned());
        $this->assertEquals($column->getCharset(), $column2->getCharset());
        $this->assertEquals($column->getCollation(), $column2->getCollation());
    }

    public function testGetName()
    {
        $table = $this->getSchemaContainer()->getSchema('sakila')->getTable('customer');
        $column = $table->getColumn('first_name');

        $this->assertEquals('first_name', $column->getName());
        $this->assertEquals($column->getName(), $column->getName(false));
        $this->assertEquals('foo.first_name', $column->getName(false, 'foo'));
    }

    public function testGetNameQuoted()
    {
        $table = $this->getSchemaContainer()->getSchema('sakila')->getTable('customer');
        $column = $table->getColumn('first_name');

        $this->assertEquals('`first_name`', $column->getName(true));
        $this->assertEquals('`foo`.`first_name`', $column->getName(true, 'foo'));
    }

    public function testGetFullName()
    {
        $table = $this->getSchemaContainer()->getSchema('sakila')->getTable('customer');
        $column = $table->getColumn('first_name');

        $this->assertEquals('sakila.customer.first_name', $column->getFullName());
        $this->assertEquals($column->getFullName(), $column->getFullName(false));
    }

    public function testGetFullNameQuoted()
    {
        $table = $this->getSchemaContainer()->getSchema('sakila')->getTable('customer');
        $column = $table->getColumn('first_name');

        $this->assertEquals('`sakila`.`customer`.`first_name`', $column->getFullName(true));
    }

    public function testGetPosition()
    {
        $table = $this->getSchemaContainer()->getSchema('sakila')->getTable('customer');
        $column = $table->getColumn('first_name');

        $this->assertEquals(2, $column->getPosition());
    }

    public function testHasDefault()
    {
        $table = $this->getSchemaContainer()->getSchema('sakila')->getTable('customer');

        $this->assertFalse($table->getColumn('address_id')->hasDefault());
        $this->assertTrue($table->getColumn('email')->hasDefault());
        $this->assertTrue($table->getColumn('active')->hasDefault());
    }

    public function testGetDefault()
    {
        $table = $this->getSchemaContainer()->getSchema('sakila')->getTable('customer');
        $column = $table->getColumn('active');

        $this->assertEquals(1, $column->getDefault());
    }

    public function testGetDefaultWithTimestamp()
    {
        $table = $this->getSchemaContainer()->getSchema('sakila')->getTable('customer');
        $column = $table->getColumn('last_update');

        $this->assertEquals('CURRENT_TIMESTAMP', $column->getDefault());
    }

    public function testGetDefaultNull()
    {
        $table = $this->getSchemaContainer()->getSchema('sakila')->getTable('customer');
        $column = $table->getColumn('first_name');

        $this->assertEquals(null, $column->getDefault());
    }

    public function testIsNullableFalse()
    {
        $table = $this->getSchemaContainer()->getSchema('sakila')->getTable('customer');
        $column = $table->getColumn('first_name');

        $this->assertFalse($column->isNullable());
    }

    public function testIsNullableTrue()
    {
        $table = $this->getSchemaContainer()->getSchema('sakila')->getTable('customer');
        $column = $table->getColumn('email');

        $this->assertTrue($column->isNullable());
    }

    public function testGetType()
    {
        $table = $this->getSchemaContainer()->getSchema('sakila')->getTable('customer');
        $column = $table->getColumn('email');

        $this->assertEquals('varchar', $column->getType());
    }

    public function testGetType2()
    {
        $table = $this->getSchemaContainer()->getSchema('sakila')->getTable('customer');
        $column = $table->getColumn('address_id');

        $this->assertEquals('smallint', $column->getType());
    }

    public function testIsAutoIncrementTrue()
    {
        $table = $this->getSchemaContainer()->getSchema('sakila')->getTable('customer');
        $column = $table->getColumn('customer_id');

        $this->assertTrue($column->isAutoIncrement());
    }

    public function testIsAutoIncrementFalse()
    {
        $table = $this->getSchemaContainer()->getSchema('sakila')->getTable('customer');
        $column = $table->getColumn('address_id');

        $this->assertFalse($column->isAutoIncrement());
    }

    public function testGetMaxlengthInteger()
    {
        $table = $this->getSchemaContainer()->getSchema('sakila')->getTable('customer');
        $column = $table->getColumn('customer_id');

        $this->assertNull($column->getMaxlength());
    }

    public function testGetMaxlengthVarChar()
    {
        $table = $this->getSchemaContainer()->getSchema('sakila')->getTable('customer');
        $column = $table->getColumn('first_name');

        $this->assertEquals(45, $column->getMaxlength());
    }

    public function testGetNumericPrecisionInteger()
    {
        $table = $this->getSchemaContainer()->getSchema('sakila')->getTable('customer');
        $column = $table->getColumn('address_id');

        $this->assertEquals(5, $column->getNumericPrecision());
    }

    public function testGetNumericPrecisionFloat()
    {
        $table = $this->getSchemaContainer()->getSchema('sakila')->getTable('payment');
        $column = $table->getColumn('amount');

        $this->assertEquals(5, $column->getNumericPrecision());
    }

    public function testGetNumericPrecisionVarChar()
    {
        $table = $this->getSchemaContainer()->getSchema('sakila')->getTable('customer');
        $column = $table->getColumn('first_name');

        $this->assertNull($column->getNumericPrecision());
    }

    public function testGetNumericScaleInteger()
    {
        $table = $this->getSchemaContainer()->getSchema('sakila')->getTable('customer');
        $column = $table->getColumn('address_id');

        $this->assertEquals(0, $column->getNumericScale());
    }

    public function testGetNumericScaleFloat()
    {
        $table = $this->getSchemaContainer()->getSchema('sakila')->getTable('payment');
        $column = $table->getColumn('amount');

        $this->assertEquals(2, $column->getNumericScale());
    }

    public function testGetNumericScaleVarChar()
    {
        $table = $this->getSchemaContainer()->getSchema('sakila')->getTable('customer');
        $column = $table->getColumn('first_name');

        $this->assertNull($column->getNumericScale());
    }

    public function testIsUnsigned()
    {
        $table = $this->getSchemaContainer()->getSchema('sakila')->getTable('customer');
        $column = $table->getColumn('customer_id');

        $this->assertTrue($column->isUnsigned());
    }

    public function testIsUnsignedFalse()
    {
        $table = $this->getSchemaContainer()->getSchema('sakila')->getTable('customer');
        $column = $table->getColumn('active');

        $this->assertFalse($column->isUnsigned());
    }

    public function testIsUnsignedVarChar()
    {
        $table = $this->getSchemaContainer()->getSchema('sakila')->getTable('customer');
        $column = $table->getColumn('first_name');

        $this->assertFalse($column->isUnsigned());
    }

    public function testGetCharset()
    {
        $table = $this->getSchemaContainer()->getSchema('sakila')->getTable('customer');
        $column = $table->getColumn('first_name');

        $this->assertEquals('utf8mb4', $column->getCharset());
    }

    public function testGetCharsetInteger()
    {
        $table = $this->getSchemaContainer()->getSchema('sakila')->getTable('customer');
        $column = $table->getColumn('address_id');

        $this->assertNull($column->getCharset());
    }

    public function testGetCollation()
    {
        $schema = $this->getSchemaContainer()->getSchema('sakila');
        $table = $schema->getTable('customer');
        $column = $table->getColumn('first_name');

        $this->assertEquals($schema->getCollation(), $column->getCollation());
    }

    public function testGetCollationInteger()
    {
        $schema = $this->getSchemaContainer()->getSchema('sakila');
        $table = $schema->getTable('customer');
        $column = $table->getColumn('address_id');

        $this->assertNull($column->getCollation());
    }

    public function testGetTable()
    {
        $table = $this->getSchemaContainer()->getSchema('sakila')->getTable('customer');
        $column = $table->getColumn('address_id');

        $this->assertSame($table, $column->getTable());
    }

    public function testIsPrimaryTrue()
    {
        $table = $this->getSchemaContainer()->getSchema('sakila')->getTable('customer');
        $column = $table->getColumn('customer_id');

        $this->assertTrue($column->isPrimary());
    }

    public function testIsPrimaryFalse()
    {
        $table = $this->getSchemaContainer()->getSchema('sakila')->getTable('customer');
        $column = $table->getColumn('first_name');

        $this->assertFalse($column->isPrimary());
    }
}
