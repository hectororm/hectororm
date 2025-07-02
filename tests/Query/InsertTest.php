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

namespace Hector\Query\Tests;

use Hector\Connection\Bind\BindParam;
use Hector\Connection\Bind\BindParamList;
use Hector\Query\Insert;
use Hector\Query\Select;
use Hector\Query\Statement\Raw;
use PHPUnit\Framework\TestCase;

class InsertTest extends TestCase
{
    public function testGetStatementEmpty()
    {
        $insert = new Insert();
        $binds = new BindParamList();

        $this->assertNull($insert->getStatement($binds));
        $this->assertEmpty($binds);
    }

    public function testGetStatementWithoutAssignment()
    {
        $insert = new Insert();
        $binds = new BindParamList();
        $insert->from('`foo`');

        $this->assertNull($insert->getStatement($binds));
        $this->assertEmpty($binds);
    }

    public function testGetStatementWithOneAssignment()
    {
        $insert = new Insert();
        $binds = new BindParamList();
        $insert->from('`foo`');
        $insert->assign('`bar`', 'value_bar');

        $this->assertEquals(
            'INSERT INTO `foo` ( `bar` ) VALUES ( :_h_0 )',
            $insert->getStatement($binds)
        );
        $this->assertEquals(
            ['_h_0' => 'value_bar'],
            array_map(fn(BindParam $bind) => $bind->getValue(), $binds->getArrayCopy())
        );
    }

    public function testGetStatementWithIgnore()
    {
        $insert = new Insert();
        $binds = new BindParamList();
        $insert->from('`foo`');
        $insert->assign('`bar`', 'value_bar');
        $insert->ignore(true);

        $this->assertEquals(
            'INSERT IGNORE INTO `foo` ( `bar` ) VALUES ( :_h_0 )',
            $insert->getStatement($binds)
        );
        $this->assertEquals(
            ['_h_0' => 'value_bar'],
            array_map(fn(BindParam $bind) => $bind->getValue(), $binds->getArrayCopy())
        );
    }

    public function testGetStatementWithSelect()
    {
        $insert = new Insert();
        $binds = new BindParamList();
        $insert->from('`foo`');
        $insert->assigns((new Select())->from('`bar`')->where('`baz`', 'value_baz'));

        $this->assertEquals(
            'INSERT INTO `foo` SELECT * FROM `bar` WHERE `baz` = :_h_0',
            $insert->getStatement($binds)
        );
        $this->assertEquals(
            ['_h_0' => 'value_baz'],
            array_map(fn(BindParam $bind) => $bind->getValue(), $binds->getArrayCopy())
        );
    }

    public function testGetStatementWithMultipleAssignments()
    {
        $insert = new Insert();
        $binds = new BindParamList();
        $insert->from('`foo`');
        $insert->assign('`bar`', 'value_bar');
        $insert->assigns(
            [
                'foo' => new Raw('CURRENT_TIMESTAMP()'),
                'baz' => 'baz_value',
                '`qux` = NOW()',
            ]
        );

        $this->assertEquals(
            'INSERT INTO `foo` ( `bar`, foo, baz, `qux` ) VALUES ( :_h_0, CURRENT_TIMESTAMP(), :_h_1, NOW() )',
            $insert->getStatement($binds)
        );
        $this->assertEquals(
            ['_h_0' => 'value_bar', '_h_1' => 'baz_value'],
            array_map(fn(BindParam $bind) => $bind->getValue(), $binds->getArrayCopy())
        );
    }

    public function testGetStatementWithEncapsulation()
    {
        $insert = new Insert();
        $binds = new BindParamList();
        $insert->from('`foo`');
        $insert->assign('`bar`', 'value_bar');

        $this->assertEquals(
            '( INSERT INTO `foo` ( `bar` ) VALUES ( :_h_0 ) )',
            $insert->getStatement($binds, true)
        );
        $this->assertEquals(
            ['_h_0' => 'value_bar'],
            array_map(fn(BindParam $bind) => $bind->getValue(), $binds->getArrayCopy())
        );
    }
}
