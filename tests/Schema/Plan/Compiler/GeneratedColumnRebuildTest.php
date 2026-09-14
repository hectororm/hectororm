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

namespace Hector\Schema\Tests\Plan\Compiler;

use Hector\Connection\Connection;
use Hector\Schema\Exception\PlanException;
use Hector\Schema\Generator\Sqlite;
use Hector\Schema\Index;
use Hector\Schema\Plan\Compiler\AutoCompiler;
use Hector\Schema\Plan\Generated;
use Hector\Schema\Plan\Plan;
use Hector\Schema\Plan\Raw;
use Hector\Schema\Table;
use PDO;
use PHPUnit\Framework\TestCase;

class GeneratedColumnRebuildTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();
        // Exercise the string-valued numeric results returned by older PDO_SQLite versions.
        $this->connection = new Connection('sqlite::memory:', options: [PDO::ATTR_STRINGIFY_FETCHES => true]);
        $this->connection->execute('PRAGMA foreign_keys = ON');
    }

    private function execute(Plan $plan): array
    {
        $schema = (new Sqlite($this->connection))->generateSchema('main');
        $statements = [...$plan->getStatements(new AutoCompiler($this->connection), $schema)];
        foreach ($statements as $statement) {
            $this->connection->execute($statement);
        }

        return $statements;
    }

    private function table(): Table
    {
        return (new Sqlite($this->connection))->generateSchema('main')->getTable('items');
    }

    private function numericRow(string $sql): array
    {
        return array_map('intval', $this->connection->fetchOne($sql));
    }

    private function createItems(bool $generated = false): void
    {
        $plan = new Plan();
        $table = $plan->create('items');
        $table->addColumn('id', 'INTEGER', autoIncrement: true)
            ->addIndex('PRIMARY', ['id'], Index::PRIMARY)
            ->addColumn('quantity', 'INTEGER', default: 0)
            ->addColumn('price', 'INTEGER', default: 0)
            ->addColumn('label', 'TEXT', default: 'quantity')
            ->addColumn('created_at', 'TEXT', default: new Raw('CURRENT_TIMESTAMP'));
        if ($generated) {
            $table->addColumn('total', 'INTEGER', generated: 'quantity * price')
                ->addColumn('bonus', 'INTEGER', generated: new Generated('total + 1', stored: true))
                ->addIndex('idx_total', ['total'])
                ->addIndex('idx_bonus', ['bonus']);
        }
        $this->execute($plan);
        $this->connection->execute("INSERT INTO items (quantity, price, label) VALUES (2, 5, 'a'), (4, 3, 'b')");
    }

    public function testStoredAddRebuildsPopulatedTableAndPreservesDefaults(): void
    {
        $this->createItems();
        $plan = new Plan();
        $plan->alter('items')->addColumn('total', 'INTEGER', generated: new Generated('quantity * price', stored: true));
        $statements = $this->execute($plan);

        $this->assertStringContainsString('GENERATED ALWAYS AS (quantity * price) STORED', $statements[1]);
        $this->assertStringNotContainsString('total', $statements[2]);
        $this->assertStringContainsString('("id", "quantity", "price", "label", "created_at") SELECT', $statements[2]);
        $this->assertSame([10, 12], array_map('intval', array_column(
            $this->connection->fetchAll('SELECT total FROM items ORDER BY id'),
            'total',
        )));
        $this->assertTrue($this->table()->getColumn('total')->isGeneratedStored());

        $this->connection->execute('UPDATE items SET quantity = 3 WHERE id = 1');
        $this->assertSame(15, $this->numericRow('SELECT total FROM items WHERE id = 1')['total']);
        $this->connection->execute('INSERT INTO items (quantity, price) VALUES (1, 7)');
        $row = $this->connection->fetchOne('SELECT id, label, created_at, total FROM items WHERE id = 3');
        $this->assertSame('quantity', $row['label']);
        $this->assertSame(7, (int)$row['total']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} /', $row['created_at']);
        $this->assertSame(1, $this->numericRow('PRAGMA foreign_keys')['foreign_keys']);
        $this->assertSame([], $this->connection->fetchAll("SELECT name FROM sqlite_master WHERE name LIKE '__htemp_%'"));
    }

    public function testRebuildPreservesBothModesDependenciesAndIndexes(): void
    {
        $this->createItems(generated: true);
        $plan = new Plan();
        $plan->alter('items')->modifyColumn('label', 'VARCHAR(50)', default: 'quantity');
        $statements = $this->execute($plan);

        $this->assertStringNotContainsString('total', $statements[2]);
        $this->assertStringNotContainsString('bonus', $statements[2]);
        $table = $this->table();
        $this->assertSame('quantity * price', $table->getColumn('total')->getGenerationExpression());
        $this->assertFalse($table->getColumn('total')->isGeneratedStored());
        $this->assertSame('total + 1', $table->getColumn('bonus')->getGenerationExpression());
        $this->assertTrue($table->getColumn('bonus')->isGeneratedStored());
        $this->assertSame(['total'], $table->getIndex('idx_total')->getColumnsName());
        $this->assertSame(['bonus'], $table->getIndex('idx_bonus')->getColumnsName());

        $drop = new Plan();
        $drop->alter('items')->dropColumn('label');
        $this->execute($drop);
        $this->connection->execute('UPDATE items SET price = 7 WHERE id = 1');
        $this->assertSame(['total' => 14, 'bonus' => 15],
            $this->numericRow('SELECT total, bonus FROM items WHERE id = 1'));
        $this->assertSame(2, $this->numericRow('SELECT COUNT(*) AS n FROM items')['n']);
    }

    public function testChangingGeneratedDefinitionsAndConvertingToOrdinaryColumn(): void
    {
        $this->createItems(generated: true);
        $add = new Plan();
        $add->alter('items')->addColumn('snapshot', 'INTEGER', default: 99);
        $this->execute($add);

        $generate = new Plan();
        $generate->alter('items')->modifyColumn('snapshot', 'INTEGER',
            generated: new Generated('total * 2', stored: true));
        $this->execute($generate);
        $this->assertSame(20, $this->numericRow('SELECT snapshot FROM items WHERE id = 1')['snapshot']);

        $changeMode = new Plan();
        $changeMode->alter('items')->modifyColumn('total', 'INTEGER',
            generated: new Generated('quantity * price * 3', stored: true));
        $this->execute($changeMode);
        $this->assertTrue($this->table()->getColumn('total')->isGeneratedStored());
        $this->assertSame(['total' => 30, 'bonus' => 31, 'snapshot' => 60],
            $this->numericRow('SELECT total, bonus, snapshot FROM items WHERE id = 1'));

        $ordinary = new Plan();
        $ordinary->alter('items')->modifyColumn('total', 'INTEGER');
        $statements = $this->execute($ordinary);
        $this->assertStringContainsString('"total"', $statements[2]);
        $this->assertStringNotContainsString('"snapshot"', $statements[2]);
        $this->assertFalse($this->table()->getColumn('total')->isGenerated());
        $this->connection->execute('UPDATE items SET quantity = 100 WHERE id = 1');
        $this->assertSame(['total' => 30, 'bonus' => 31, 'snapshot' => 60],
            $this->numericRow('SELECT total, bonus, snapshot FROM items WHERE id = 1'));
    }

    public function testCombinedRenamesRewriteReferencesButKeepSqlStringsAndIndexes(): void
    {
        $this->createItems(generated: true);
        $add = new Plan();
        $add->alter('items')
            ->addColumn('summary', 'TEXT', generated: "label || ':' || quantity || ':quantity'")
            ->addIndex('idx_quantity', ['quantity']);
        $this->execute($add);

        $rename = new Plan();
        $rename->alter('items')
            ->renameColumn('QUANTITY', 'amount')
            ->renameColumn('amount', 'units')
            ->renameColumn('total', 'computed_total')
            ->modifyColumn('label', 'VARCHAR(50)', default: 'quantity');
        $this->execute($rename);

        $table = $this->table();
        $this->assertSame('"units" * price', $table->getColumn('computed_total')->getGenerationExpression());
        $this->assertSame('"computed_total" + 1', $table->getColumn('bonus')->getGenerationExpression());
        $this->assertSame("label || ':' || \"units\" || ':quantity'", $table->getColumn('summary')->getGenerationExpression());
        $this->assertSame(['units'], $table->getIndex('idx_quantity')->getColumnsName());
        $this->assertSame(['computed_total'], $table->getIndex('idx_total')->getColumnsName());
        $this->assertSame(['id', 'units', 'price', 'label', 'created_at', 'computed_total', 'bonus', 'summary'],
            array_map(static fn($column): string => $column->getName(), iterator_to_array($table->getColumns(), false)));
        $this->assertSame(['units' => 2, 'computed_total' => 10, 'bonus' => 11],
            $this->numericRow('SELECT units, computed_total, bonus FROM items WHERE id = 1'));
        $this->assertSame('a:2:quantity', $this->connection->fetchOne('SELECT summary FROM items WHERE id = 1')['summary']);
        $this->connection->execute('UPDATE items SET units = 3 WHERE id = 1');
        $this->assertSame(15, $this->numericRow('SELECT computed_total FROM items WHERE id = 1')['computed_total']);
    }

    public function testNoWritableMappingFailsBeforeChangingTheDatabase(): void
    {
        $create = new Plan();
        $create->create('items')->addColumn('source', 'INTEGER')->addColumn('computed', 'INTEGER', generated: 'source * 2');
        $this->execute($create);
        $this->connection->execute('INSERT INTO items (source) VALUES (5)');

        $alter = new Plan();
        $alter->alter('items')->dropColumn('source')
            ->modifyColumn('computed', 'INTEGER', generated: '7')
            ->addColumn('replacement', 'INTEGER', default: 1);
        try {
            $this->execute($alter);
            $this->fail('A rebuild without a writable mapping must not use SELECT *');
        } catch (PlanException $exception) {
            $this->assertStringContainsString('no surviving writable column to migrate', $exception->getMessage());
        }

        $this->assertSame(['source' => 5, 'computed' => 10], $this->numericRow('SELECT * FROM items'));
        $this->assertSame(1, $this->numericRow('PRAGMA foreign_keys')['foreign_keys']);
        $this->assertSame([], $this->connection->fetchAll("SELECT name FROM sqlite_master WHERE name LIKE '__htemp_%'"));
    }

    public function testNumericQuotedIdentifiersAreNotConfusedWithNumericLiterals(): void
    {
        $create = new Plan();
        $create->create('items')->addColumn('2', 'INTEGER')->addColumn('computed', 'INTEGER', generated: '2 + "2"');
        $this->execute($create);
        $this->connection->execute('INSERT INTO items ("2") VALUES (5)');

        $alter = new Plan();
        $alter->alter('items')->renameColumn('2', '3')->modifyColumn('computed', 'INTEGER',
            generated: new Generated('2 + "3"', stored: true));
        $this->execute($alter);

        $this->assertSame(['3' => 5, 'computed' => 7], $this->numericRow('SELECT * FROM items'));
        $this->assertSame('2 + "3"', $this->table()->getColumn('computed')->getGenerationExpression());
    }

    public function testSelfReferencingForeignKeySurvivesRenamesAndStoredAdd(): void
    {
        $create = new Plan();
        $create->create('items')->addColumn('id', 'INTEGER', autoIncrement: true)
            ->addColumn('parent_id', 'INTEGER', nullable: true)
            ->addForeignKey('fk_parent', ['parent_id'], 'items', ['id']);
        $this->execute($create);
        $this->connection->execute('INSERT INTO items (id, parent_id) VALUES (1, NULL), (2, 1)');

        $alter = new Plan();
        $alter->alter('items')
            ->addColumn('stored_parent', 'INTEGER', generated: new Generated('coalesce(parent_id, 0)', stored: true))
            ->renameColumn('id', 'entry_id')
            ->renameColumn('parent_id', 'ancestor_id');
        $this->execute($alter);

        $this->assertSame([], $this->connection->fetchAll('PRAGMA foreign_key_check'));
        $this->assertSame(['entry_id' => 2, 'ancestor_id' => 1, 'stored_parent' => 1],
            $this->numericRow('SELECT * FROM items WHERE entry_id = 2'));
        $foreignKeys = iterator_to_array($this->table()->getForeignKeys(), false);
        $this->assertSame(['ancestor_id'], $foreignKeys[0]->getColumnsName());
        $this->assertSame(['entry_id'], $foreignKeys[0]->getReferencedColumnsName());
    }
}
