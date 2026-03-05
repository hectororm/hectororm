<?php
/*
 * This file is part of Hector ORM.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2025 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

declare(strict_types=1);

namespace Hector\Query\Tests;

use Hector\Connection\Bind\BindParamList;
use Hector\Connection\Driver\DriverCapabilities;
use Hector\Query\Component\Columns;
use Hector\Query\Component\Join;
use Hector\Query\Component\Table;
use Hector\Query\Delete;
use Hector\Query\Helper;
use Hector\Query\Insert;
use Hector\Query\Select;
use Hector\Query\Update;
use PHPUnit\Framework\TestCase;

class DriverAwareQuotingTest extends TestCase
{
    private function createCapabilities(string $quote): DriverCapabilities
    {
        $capabilities = $this->createMock(DriverCapabilities::class);
        $capabilities->method('getIdentifierQuote')->willReturn($quote);

        return $capabilities;
    }

    // -------------------------------------------------------------------------
    // Helper tests
    // -------------------------------------------------------------------------

    public function testHelperQuoteDefaultBacktick(): void
    {
        $this->assertSame('`foo`', Helper::quote('foo'));
    }

    public function testHelperQuoteDoubleQuote(): void
    {
        $this->assertSame('"foo"', Helper::quote('foo', '"'));
    }

    public function testHelperQuoteEscapesEmbeddedBacktick(): void
    {
        $this->assertSame('`foo``bar`', Helper::quote('foo`bar'));
    }

    public function testHelperQuoteEscapesEmbeddedDoubleQuote(): void
    {
        $this->assertSame('"foo""bar"', Helper::quote('foo"bar', '"'));
    }

    public function testHelperQuoteNull(): void
    {
        $this->assertNull(Helper::quote(null));
    }

    public function testHelperTrimDefaultBacktick(): void
    {
        $this->assertSame('foo', Helper::trim('`foo`'));
    }

    public function testHelperTrimDoubleQuote(): void
    {
        $this->assertSame('foo', Helper::trim('"foo"', '"'));
    }

    public function testHelperTrimNull(): void
    {
        $this->assertNull(Helper::trim(null));
    }

    // -------------------------------------------------------------------------
    // Component\Columns tests
    // -------------------------------------------------------------------------

    public function testColumnsBacktickQuoting(): void
    {
        $columns = new Columns();
        $columns->column('foo', 'f');
        $binds = new BindParamList();

        $this->assertSame(
            'foo AS `f`',
            $columns->getStatement($binds)
        );
    }

    public function testColumnsDoubleQuoteQuoting(): void
    {
        $columns = new Columns();
        $columns->column('foo', 'f');
        $binds = new BindParamList();

        $this->assertSame(
            'foo AS "f"',
            $columns->getStatement($binds, $this->createCapabilities('"'))
        );
    }

    public function testColumnsMultipleWithDoubleQuote(): void
    {
        $columns = new Columns();
        $columns->column('foo', 'f');
        $columns->column('bar', 'b');
        $binds = new BindParamList();

        $this->assertSame(
            'foo AS "f", bar AS "b"',
            $columns->getStatement($binds, $this->createCapabilities('"'))
        );
    }

    // -------------------------------------------------------------------------
    // Component\Table tests
    // -------------------------------------------------------------------------

    public function testTableBacktickQuoting(): void
    {
        $table = new Table();
        $table->table('foo', 'f');
        $binds = new BindParamList();

        $this->assertSame(
            'foo AS `f`',
            $table->getStatement($binds)
        );
    }

    public function testTableDoubleQuoteQuoting(): void
    {
        $table = new Table();
        $table->table('foo', 'f');
        $binds = new BindParamList();

        $this->assertSame(
            'foo AS "f"',
            $table->getStatement($binds, $this->createCapabilities('"'))
        );
    }

    public function testTableMultipleWithDoubleQuote(): void
    {
        $table = new Table();
        $table->table('foo', 'f');
        $table->table('bar', 'b');
        $binds = new BindParamList();

        $this->assertSame(
            'foo AS "f", bar AS "b"',
            $table->getStatement($binds, $this->createCapabilities('"'))
        );
    }

    // -------------------------------------------------------------------------
    // Component\Join tests
    // -------------------------------------------------------------------------

    public function testJoinAliasDoubleQuoteQuoting(): void
    {
        $join = new Join();
        $join->join(Join::LEFT_JOIN, 'bar', 'bar.bar_id = f.foo_id', 'b');
        $binds = new BindParamList();

        $this->assertSame(
            'LEFT JOIN bar AS "b" ON ( bar.bar_id = f.foo_id )',
            $join->getStatement($binds, $this->createCapabilities('"'))
        );
    }

    public function testJoinAliasBacktickQuoting(): void
    {
        $join = new Join();
        $join->join(Join::LEFT_JOIN, 'bar', 'bar.bar_id = f.foo_id', 'b');
        $binds = new BindParamList();

        $this->assertSame(
            'LEFT JOIN bar AS `b` ON ( bar.bar_id = f.foo_id )',
            $join->getStatement($binds)
        );
    }

    // -------------------------------------------------------------------------
    // Select tests
    // -------------------------------------------------------------------------

    public function testSelectWithDoubleQuoteCapabilities(): void
    {
        $select = new Select();
        $binds = new BindParamList();
        $select->from('foo', 'f');

        $this->assertSame(
            'SELECT * FROM foo AS "f"',
            $select->getStatement($binds, $this->createCapabilities('"'))
        );
    }

    public function testSelectWithColumnsAndDoubleQuote(): void
    {
        $select = new Select();
        $binds = new BindParamList();
        $select->from('foo', 'f');
        $select->column('bar', 'b');

        $this->assertSame(
            'SELECT bar AS "b" FROM foo AS "f"',
            $select->getStatement($binds, $this->createCapabilities('"'))
        );
    }

    public function testSelectWithJoinAndDoubleQuote(): void
    {
        $select = new Select();
        $binds = new BindParamList();
        $select->from('foo', 'f');
        $select->leftJoin('bar', 'bar.bar_id = f.foo_id');

        $this->assertSame(
            'SELECT * FROM foo AS "f" LEFT JOIN bar ON ( bar.bar_id = f.foo_id )',
            $select->getStatement($binds, $this->createCapabilities('"'))
        );
    }

    public function testSelectDefaultBacktickWithoutCapabilities(): void
    {
        $select = new Select();
        $binds = new BindParamList();
        $select->from('foo', 'f');

        $this->assertSame(
            'SELECT * FROM foo AS `f`',
            $select->getStatement($binds)
        );
    }

    public function testSelectSubqueryPropagatesCapabilities(): void
    {
        $select = new Select();
        $binds = new BindParamList();
        $select->from(
            (new Select())->from('bar', 'b'),
            'subquery'
        );

        $this->assertSame(
            'SELECT * FROM ( SELECT * FROM bar AS "b" ) AS "subquery"',
            $select->getStatement($binds, $this->createCapabilities('"'))
        );
    }

    // -------------------------------------------------------------------------
    // Insert tests
    // -------------------------------------------------------------------------

    public function testInsertWithDoubleQuoteCapabilities(): void
    {
        $insert = new Insert();
        $binds = new BindParamList();
        $insert->from('foo');
        $insert->assign('bar', 'value_bar');

        // Insert doesn't quote table aliases, but the capabilities propagate
        // to sub-statements if any. Verify no regression.
        $this->assertSame(
            'INSERT INTO foo ( bar ) VALUES ( :_h_0 )',
            $insert->getStatement($binds, $this->createCapabilities('"'))
        );
    }

    // -------------------------------------------------------------------------
    // Update tests
    // -------------------------------------------------------------------------

    public function testUpdateWithDoubleQuoteCapabilities(): void
    {
        $update = new Update();
        $binds = new BindParamList();
        $update->from('foo', 'f');
        $update->assign('bar', 'value_bar');

        $this->assertSame(
            'UPDATE foo AS "f" SET bar = :_h_0',
            $update->getStatement($binds, $this->createCapabilities('"'))
        );
    }

    // -------------------------------------------------------------------------
    // Delete tests
    // -------------------------------------------------------------------------

    public function testDeleteWithDoubleQuoteCapabilities(): void
    {
        $delete = new Delete();
        $binds = new BindParamList();
        $delete->from('foo', 'f');
        $delete->where('bar', '=', 'baz');

        $this->assertSame(
            'DELETE FROM foo AS "f" WHERE bar = :_h_0',
            $delete->getStatement($binds, $this->createCapabilities('"'))
        );
    }

    // -------------------------------------------------------------------------
    // Null capabilities (fallback to backtick)
    // -------------------------------------------------------------------------

    public function testNullCapabilitiesFallsBackToBacktick(): void
    {
        $select = new Select();
        $binds = new BindParamList();
        $select->from('foo', 'f');

        $this->assertSame(
            'SELECT * FROM foo AS `f`',
            $select->getStatement($binds, null)
        );
    }

    public function testExplicitBacktickCapabilities(): void
    {
        $select = new Select();
        $binds = new BindParamList();
        $select->from('foo', 'f');

        $this->assertSame(
            'SELECT * FROM foo AS `f`',
            $select->getStatement($binds, $this->createCapabilities('`'))
        );
    }
}
