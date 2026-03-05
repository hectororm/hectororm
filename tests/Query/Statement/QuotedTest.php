<?php
/*
 * This file is part of Hector ORM.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2026 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

namespace Hector\Query\Tests\Statement;

use Hector\Connection\Bind\BindParamList;
use Hector\Connection\Driver\DriverCapabilities;
use Hector\Query\Statement\Quoted;
use PHPUnit\Framework\TestCase;

class QuotedTest extends TestCase
{
    private function createCapabilities(string $quote): DriverCapabilities
    {
        $capabilities = $this->createMock(DriverCapabilities::class);
        $capabilities->method('getIdentifierQuote')->willReturn($quote);

        return $capabilities;
    }

    public function testSimpleIdentifier(): void
    {
        $quoted = new Quoted('foo');
        $binds = new BindParamList();

        $this->assertSame('`foo`', $quoted->getStatement($binds));
    }

    public function testCompositeIdentifier(): void
    {
        $quoted = new Quoted('schema.table.column');
        $binds = new BindParamList();

        $this->assertSame('`schema`.`table`.`column`', $quoted->getStatement($binds));
    }

    public function testWildcardPreserved(): void
    {
        $quoted = new Quoted('table.*');
        $binds = new BindParamList();

        $this->assertSame('`table`.*', $quoted->getStatement($binds));
    }

    public function testWildcardOnly(): void
    {
        $quoted = new Quoted('*');
        $binds = new BindParamList();

        $this->assertSame('*', $quoted->getStatement($binds));
    }

    public function testPostgreSQLDriver(): void
    {
        $quoted = new Quoted('foo');
        $binds = new BindParamList();
        $capabilities = $this->createCapabilities('"');

        $this->assertSame('"foo"', $quoted->getStatement($binds, $capabilities));
    }

    public function testCompositeWithPostgreSQLDriver(): void
    {
        $quoted = new Quoted('schema.table.column');
        $binds = new BindParamList();
        $capabilities = $this->createCapabilities('"');

        $this->assertSame('"schema"."table"."column"', $quoted->getStatement($binds, $capabilities));
    }

    public function testWildcardWithPostgreSQLDriver(): void
    {
        $quoted = new Quoted('table.*');
        $binds = new BindParamList();
        $capabilities = $this->createCapabilities('"');

        $this->assertSame('"table".*', $quoted->getStatement($binds, $capabilities));
    }

    public function testAlreadyQuotedInput(): void
    {
        $quoted = new Quoted('`foo`.`bar`');
        $binds = new BindParamList();

        $this->assertSame('`foo`.`bar`', $quoted->getStatement($binds));
    }

    public function testAlreadyQuotedInputWithDifferentDriver(): void
    {
        $quoted = new Quoted('`foo`.`bar`');
        $binds = new BindParamList();
        $capabilities = $this->createCapabilities('"');

        $this->assertSame('"foo"."bar"', $quoted->getStatement($binds, $capabilities));
    }

    public function testEmbeddedQuoteEscaped(): void
    {
        $quoted = new Quoted('foo"bar');
        $binds = new BindParamList();
        $capabilities = $this->createCapabilities('"');

        $this->assertSame('"foo""bar"', $quoted->getStatement($binds, $capabilities));
    }

    public function testTwoPartIdentifier(): void
    {
        $quoted = new Quoted('table.column');
        $binds = new BindParamList();

        $this->assertSame('`table`.`column`', $quoted->getStatement($binds));
    }
}
