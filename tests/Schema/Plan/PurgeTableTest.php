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

declare(strict_types=1);

namespace Hector\Schema\Tests\Plan;

use Hector\Schema\Plan\Compiler\MySQLCompiler;
use Hector\Schema\Plan\Compiler\SqliteCompiler;
use Hector\Schema\Plan\Plan;
use Hector\Schema\Plan\PurgeTable;
use Hector\Schema\Table;
use PHPUnit\Framework\TestCase;

class PurgeTableTest extends TestCase
{
    public function testPlanAcceptsNamesAndTablesAndPreservesOrder(): void
    {
        $table = $this->createMock(Table::class);
        $table->method('getName')->willReturn('items');
        $plan = new Plan();

        $this->assertSame($plan, $plan->purge('items'));
        $this->assertSame($plan, $plan->purge($table, resetIncrement: true));
        $this->assertCount(2, $plan);
        [$first, $second] = $plan->getArrayCopy();
        $this->assertInstanceOf(PurgeTable::class, $first);
        $this->assertInstanceOf(PurgeTable::class, $second);
        $this->assertSame('items', $first->getObjectName());
        $this->assertSame('items', $second->getObjectName());
        $this->assertFalse($first->resetIncrement());
        $this->assertTrue($second->resetIncrement());
    }

    public function testMySQLCompilationWithoutSchema(): void
    {
        $plan = new Plan();
        $plan->raw('SELECT 1');
        $plan->purge('odd`name');
        $plan->purge('odd`name', resetIncrement: true);
        $plan->alter('odd`name')->modifyColumn('required', 'INT');

        $this->assertSame([
            'SELECT 1',
            'DELETE FROM `odd``name`',
            'TRUNCATE TABLE `odd``name`',
            'ALTER TABLE `odd``name` MODIFY COLUMN `required` INT NOT NULL',
        ], iterator_to_array($plan->getStatements(new MySQLCompiler()), false));
    }

    public function testSqliteCompilationWithoutSchemaEscapesIdentifierAndSequenceValue(): void
    {
        $plan = new Plan();
        $plan->purge('odd"name\'s');
        $plan->purge('odd"name\'s', resetIncrement: true);
        $plan->alter('items')->modifyColumn('required', 'INT');

        $this->assertSame([
            'DELETE FROM "odd""name\'s"',
            'DELETE FROM "odd""name\'s"',
            'DELETE FROM sqlite_sequence WHERE name = \'odd"name\'\'s\'',
            'ALTER TABLE "items" MODIFY COLUMN "required" INT NOT NULL',
        ], iterator_to_array($plan->getStatements(new SqliteCompiler()), false));
    }
}
