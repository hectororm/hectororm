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

use Hector\Schema\CheckConstraint;
use Hector\Schema\Column;
use Hector\Schema\Exception\NotFoundException;
use Hector\Schema\Exception\SchemaException;
use Hector\Schema\Schema;
use Hector\Schema\Table;
use PHPUnit\Framework\TestCase;

class TableCheckConstraintTest extends TestCase
{
    public function testNamedAndUnnamedConstraintsSurviveSchemaSerialization(): void
    {
        $checks = [
            new CheckConstraint(null, 'price >= 0', true),
            new CheckConstraint('chk_period', 'ends_at >= starts_at', false),
            new CheckConstraint(null, "label <> 'CHECK (price)'", null),
        ];
        $column = new Column('price', 0, null, true, 'decimal');
        $table = new Table(
            'main',
            Table::TYPE_TABLE,
            'products',
            columns: ['price' => $column],
            checks: $checks,
        );
        $schema = new Schema('default', 'main', 'utf8', tables: ['products' => $table]);

        self::assertTrue($table->hasCheckMetadata());
        self::assertSame($checks, iterator_to_array($table->getChecks()));
        self::assertTrue($table->hasCheck('chk_period'));
        self::assertSame($checks[1], $table->getCheck('chk_period'));
        // Unnamed constraints are not exposed under synthetic numeric names.
        self::assertFalse($table->hasCheck('0'));
        self::assertFalse($table->hasCheck('2'));
        self::assertCount(1, $table);
        self::assertSame(['price' => $column], iterator_to_array($table));

        /** @var Schema $restoredSchema */
        $restoredSchema = unserialize(serialize($schema));
        $restoredTable = $restoredSchema->getTable('products');
        $restoredChecks = iterator_to_array($restoredTable->getChecks());

        self::assertTrue($restoredTable->hasCheckMetadata());
        self::assertCount(3, $restoredChecks);
        self::assertSame($restoredSchema, $restoredTable->getSchema());
        self::assertSame($restoredTable, $restoredTable->getColumn('price')->getTable());

        foreach ($checks as $index => $check) {
            self::assertNotSame($check, $restoredChecks[$index]);
            self::assertSame($check->getName(), $restoredChecks[$index]->getName());
            self::assertSame($check->getExpression(), $restoredChecks[$index]->getExpression());
            self::assertSame($check->isEnforced(), $restoredChecks[$index]->isEnforced());
            self::assertSame($restoredTable, $restoredChecks[$index]->getTable());
        }
    }

    public function testKnownEmptyMetadataSurvivesSerialization(): void
    {
        $table = new Table('main', Table::TYPE_TABLE, 'products', checks: []);
        /** @var Table $restored */
        $restored = unserialize(serialize($table));

        self::assertTrue($table->hasCheckMetadata());
        self::assertTrue($restored->hasCheckMetadata());
        self::assertSame([], iterator_to_array($restored->getChecks()));
        self::assertFalse($restored->hasCheck('chk_price'));

        $this->expectException(NotFoundException::class);
        $restored->getCheck('chk_price');
    }

    public function testMissingNamedConstraintIsNotFoundAmongUnnamedConstraints(): void
    {
        $table = new Table('main', Table::TYPE_TABLE, 'products', checks: [
            new CheckConstraint(null, 'price >= 0', true),
        ]);

        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('CHECK "0" not found in table "products"');
        $table->getCheck('0');
    }

    public function testUnknownMetadataSurvivesSerialization(): void
    {
        $table = new Table('main', Table::TYPE_TABLE, 'products');
        /** @var Table $restored */
        $restored = unserialize(serialize($table));

        self::assertFalse($table->hasCheckMetadata());
        self::assertFalse($restored->hasCheckMetadata());

        $this->expectException(SchemaException::class);
        $this->expectExceptionMessage('CHECK metadata is unavailable for table "products"');
        iterator_to_array($restored->getChecks());
    }

    public function testHasCheckRejectsUnknownMetadata(): void
    {
        $table = new Table('main', Table::TYPE_TABLE, 'products');

        $this->expectException(SchemaException::class);
        $this->expectExceptionMessage('CHECK metadata is unavailable');
        $table->hasCheck('chk_price');
    }

    public function testGetCheckRejectsUnknownMetadata(): void
    {
        $table = new Table('main', Table::TYPE_TABLE, 'products');

        $this->expectException(SchemaException::class);
        $this->expectExceptionMessage('CHECK metadata is unavailable');
        $table->getCheck('chk_price');
    }

    public function testLegacyPayloadDoesNotImplyAnEmptyCheckList(): void
    {
        // The pre-CHECK Table serialization format, with no CHECK metadata field.
        $legacy = [
            'schema_name' => 'main',
            'name' => 'products',
            'type' => Table::TYPE_TABLE,
            'charset' => null,
            'collation' => null,
            'columns' => ['price' => new Column('price', 0, null, true, 'decimal')],
            'indexes' => [],
            'foreign_keys' => [],
        ];
        $table = new Table('main', Table::TYPE_TABLE, 'placeholder', checks: []);
        $table->__unserialize($legacy);

        self::assertSame('products', $table->getName());
        self::assertFalse($table->hasCheckMetadata());
        self::assertSame($table, $table->getColumn('price')->getTable());
        self::assertCount(1, $table);

        /** @var Table $restored */
        $restored = unserialize(serialize($table));
        self::assertFalse($restored->hasCheckMetadata());

        $this->expectException(SchemaException::class);
        $this->expectExceptionMessage('CHECK metadata is unavailable');
        iterator_to_array($restored->getChecks());
    }

    public function testDuplicateNamesArePreservedButCannotBeResolvedUnambiguously(): void
    {
        $table = new Table('main', Table::TYPE_TABLE, 'products', checks: [
            new CheckConstraint('chk_price', 'price >= 0', true),
            new CheckConstraint('chk_price', 'price < 100', true),
        ]);
        /** @var Table $restored */
        $restored = unserialize(serialize($table));

        self::assertCount(2, iterator_to_array($restored->getChecks()));
        self::assertTrue($restored->hasCheck('chk_price'));

        $this->expectException(SchemaException::class);
        $this->expectExceptionMessage('Ambiguous CHECK name "chk_price" in table "products"');
        $restored->getCheck('chk_price');
    }
}
