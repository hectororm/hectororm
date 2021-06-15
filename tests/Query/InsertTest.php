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

use Hector\Query\Insert;
use Hector\Query\Statement\Raw;
use PHPUnit\Framework\TestCase;

class InsertTest extends TestCase
{
    public function testGetStatementEmpty()
    {
        $insert = new Insert();
        $binding = [];

        $this->assertNull($insert->getStatement($binding));
        $this->assertEmpty($binding);
    }

    public function testGetStatementWithoutAssignment()
    {
        $insert = new Insert();
        $binding = [];
        $insert->from('`foo`');

        $this->assertNull($insert->getStatement($binding));
        $this->assertEmpty($binding);
    }

    public function testGetStatementWithOneAssignment()
    {
        $insert = new Insert();
        $binding = [];
        $insert->from('`foo`');
        $insert->assign('`bar`', 'value_bar');

        $this->assertEquals(
            'INSERT INTO `foo` SET `bar` = ?',
            $insert->getStatement($binding)
        );
        $this->assertEquals(['value_bar'], $binding);
    }

    public function testGetStatementWithMultipleAssignments()
    {
        $insert = new Insert();
        $binding = [];
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
            'INSERT INTO `foo` SET `bar` = ?, foo = CURRENT_TIMESTAMP(), baz = ?, `qux` = NOW()',
            $insert->getStatement($binding)
        );
        $this->assertEquals(['value_bar', 'baz_value'], $binding);
    }

    public function testGetStatementWithEncapsulation()
    {
        $insert = new Insert();
        $binding = [];
        $insert->from('`foo`');
        $insert->assign('`bar`', 'value_bar');

        $this->assertEquals(
            '( INSERT INTO `foo` SET `bar` = ? )',
            $insert->getStatement($binding, true)
        );
        $this->assertEquals(['value_bar'], $binding);
    }
}
