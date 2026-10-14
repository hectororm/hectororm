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

namespace Hector\Schema\Tests;

use Hector\Connection\Connection;
use Hector\Schema\Generator\Sqlite;
use Hector\Schema\Index;
use Hector\Schema\Schema;
use Hector\Schema\Table;
use PHPUnit\Framework\TestCase;

class NativeDefinitionTest extends TestCase
{
    public function testIntrospectionAndSerializationKeepDefinitionsOnTheirRespectiveObjects(): void
    {
        $connection = new Connection('sqlite::memory:');
        $definitions = [
            'items' => 'CREATE TABLE items (id INTEGER PRIMARY KEY, label TEXT UNIQUE, price INT CHECK(price >= 0))',
            'other_items' => 'CREATE TABLE other_items (value TEXT)',
            'idx_price' => 'CREATE INDEX idx_price ON items((price + 1)) WHERE price > 0',
            'item_view' => 'CREATE VIEW item_view AS SELECT label, price FROM items',
            'audit_item' => 'CREATE TRIGGER audit_item AFTER INSERT ON items BEGIN ' .
                'INSERT INTO other_items VALUES (NEW.label); END',
            'view_insert' => 'CREATE TRIGGER view_insert INSTEAD OF INSERT ON item_view BEGIN ' .
                'INSERT INTO items(label, price) VALUES (NEW.label, NEW.price); END',
        ];
        foreach ($definitions as $sql) {
            $connection->execute($sql);
        }

        $schema = (new Sqlite($connection))->generateSchema('main');
        /** @var Schema $restored */
        $restored = unserialize(serialize($schema));
        foreach ([$schema, $restored] as $result) {
            $table = $result->getTable('items');
            self::assertSame($definitions['items'], $table->getDefinition());
            self::assertSame(['audit_item' => $definitions['audit_item']], $table->getTriggerDefinitions());
            self::assertSame($definitions['idx_price'], $table->getIndex('idx_price')->getDefinition());
            self::assertFalse($table->getIndex('idx_price')->isImplicit());
            self::assertSame($table, $table->getIndex('idx_price')->getTable());
            self::assertTrue($table->getIndex('sqlite_autoindex_items_1')->isImplicit());
            self::assertNull($table->getIndex('sqlite_autoindex_items_1')->getDefinition());
            self::assertSame([], $result->getTable('other_items')->getTriggerDefinitions());
            self::assertSame($definitions['other_items'], $result->getTable('other_items')->getDefinition());
            $views = iterator_to_array($result->getTables(Table::TYPE_VIEW));
            self::assertCount(1, $views);
            self::assertSame($definitions['item_view'], $views[0]->getDefinition());
            self::assertSame(['view_insert' => $definitions['view_insert']], $views[0]->getTriggerDefinitions());
        }
    }

    public function testLegacyPayloadsKeepNativeDefinitionsUnavailable(): void
    {
        $index = new Index('idx', Index::INDEX, ['price']);
        $table = new Table('main', Table::TYPE_TABLE, 'items', indexes: ['idx' => $index]);
        $legacyIndex = $index->__serialize();
        unset($legacyIndex['definition'], $legacyIndex['implicit']);
        $legacyTable = $table->__serialize();
        unset($legacyTable['definition'], $legacyTable['trigger_definitions']);

        $index->__unserialize($legacyIndex);
        $table->__unserialize($legacyTable);
        /** @var Table $restored */
        $restored = unserialize(serialize($table));

        self::assertNull($restored->getDefinition());
        self::assertNull($restored->getTriggerDefinitions());
        self::assertNull($restored->getIndex('idx')->getDefinition());
        self::assertFalse($restored->getIndex('idx')->isImplicit());
        self::assertSame($restored, $restored->getIndex('idx')->getTable());
    }
}
