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

namespace Hector\Orm\Tests\Mapper;

use Hector\Connection\Connection;
use Hector\Connection\Log\Logger;
use Hector\Orm\Attributes\Table;
use Hector\Orm\Entity\Entity;
use Hector\Orm\Entity\MagicEntity;
use Hector\Orm\Entity\ReflectionEntity;
use Hector\Orm\Exception\OrmException;
use Hector\Orm\Orm;
use Hector\Schema\Generator\Sqlite;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

class GeneratedColumnsTest extends TestCase
{
    private Connection $connection;
    private Logger $logger;
    private Orm $orm;
    private ?Orm $previousOrm;
    private array $previousReflections;

    protected function setUp(): void
    {
        $this->previousOrm = Orm::$instance;
        Orm::$instance = null;
        $reflections = new ReflectionProperty(ReflectionEntity::class, 'reflections');
        $reflections->setAccessible(true);
        $this->previousReflections = $reflections->getValue();
        $reflections->setValue(null, []);

        $this->logger = new Logger();
        $this->connection = new Connection('sqlite::memory:', logger: $this->logger);
        $this->connection->execute(
            'CREATE TABLE generated_items (id INTEGER PRIMARY KEY AUTOINCREMENT, '
            . 'quantity INTEGER NOT NULL, price INTEGER NOT NULL, '
            . 'total INTEGER GENERATED ALWAYS AS (quantity * price) VIRTUAL, '
            . 'stored_total INTEGER GENERATED ALWAYS AS (quantity * price) STORED)',
        );
        $this->orm = new Orm($this->connection, (new Sqlite($this->connection))->generateSchemas('main'));
    }

    protected function tearDown(): void
    {
        Orm::$instance = $this->previousOrm;
        $reflections = new ReflectionProperty(ReflectionEntity::class, 'reflections');
        $reflections->setAccessible(true);
        $reflections->setValue(null, $this->previousReflections);
    }

    /** @dataProvider insertEntities */
    public function testInsertOmitsGeneratedColumns(string $class, ?int $generated): void
    {
        $entity = new $class();
        $entity->quantity = 2;
        $entity->price = 5;
        if (null !== $generated) {
            // Hydration also exercises MagicMapper with attributes populated without __set().
            $this->orm->getMapper($entity)->hydrateEntity($entity, [
                'total' => $generated,
                'stored_total' => $generated,
            ]);
        }

        $offset = count($this->logger);
        $entity->save();

        $this->assertSame(1, $entity->id);
        $row = $this->connection->fetchOne('SELECT * FROM generated_items WHERE id = 1');
        $this->assertSame(10, (int)$row['total']);
        $this->assertSame(10, (int)$row['stored_total']);
        $writes = $this->writesSince($offset);
        $this->assertCount(1, $writes);
        $this->assertStringNotContainsString('total', $writes[0]);
        if (GeneratedMissingProperties::class !== $class) {
            $this->assertSame(10, $entity->total);
            $this->assertSame(10, $entity->stored_total);
        }
    }

    public static function insertEntities(): array
    {
        return [
            'generic null' => [GeneratedGenericItem::class, null],
            'generic assigned' => [GeneratedGenericItem::class, 999],
            'generic uninitialized' => [GeneratedUninitializedItem::class, null],
            'generic absent' => [GeneratedMissingProperties::class, null],
            'magic absent' => [GeneratedMagicItem::class, null],
            'magic hydrated' => [GeneratedMagicItem::class, 999],
        ];
    }

    /** @dataProvider mappedEntities */
    public function testUpdateOmitsGeneratedValuesAndRefreshesThem(string $class): void
    {
        $this->connection->execute('INSERT INTO generated_items (quantity, price) VALUES (2, 5)');
        $entity = $class::find(1);
        $this->assertSame(10, $entity->total);
        $this->assertSame(10, $entity->stored_total);
        $entity->quantity = 3;
        $offset = count($this->logger);
        $entity->save();
        $this->assertSame(15, $entity->total);
        $this->assertSame(15, $entity->stored_total);

        $writes = $this->writesSince($offset);
        $this->assertCount(1, $writes);
        $this->assertStringNotContainsString('total', $writes[0]);

        $entity->quantity = 4;
        $this->orm->getMapper($entity)->hydrateEntity($entity, ['total' => 999, 'stored_total' => 999]);
        $offset = count($this->logger);
        $entity->save();
        $this->assertSame(20, $entity->total);
        $this->assertSame(20, $entity->stored_total);
        $writes = $this->writesSince($offset);
        $this->assertCount(1, $writes);
        $this->assertStringNotContainsString('total', $writes[0]);
    }

    /** @dataProvider mappedEntities */
    public function testGeneratedOnlyChangeRefreshesWithoutAnUpdate(string $class): void
    {
        $this->connection->execute('INSERT INTO generated_items (quantity, price) VALUES (2, 5)');
        $entity = $class::find(1);
        $mapper = $this->orm->getMapper($entity);
        $mapper->hydrateEntity($entity, ['total' => 999, 'stored_total' => 999]);
        $this->assertSame(999, $mapper->collectEntity($entity)['total']);

        $offset = count($this->logger);
        $entity->save();

        $this->assertSame([], $this->writesSince($offset));
        $this->assertSame(10, $entity->total);
        $this->assertSame(10, $entity->stored_total);
        $this->assertSame([], $mapper->getEntityAlteration($entity));
    }

    public static function mappedEntities(): array
    {
        return [
            'generic' => [GeneratedGenericItem::class],
            'magic' => [GeneratedMagicItem::class],
        ];
    }

    /** @dataProvider generatedAssignments */
    public function testMagicAssignmentRejectsGeneratedColumns(string $column, ?int $value, bool $loaded): void
    {
        if ($loaded) {
            $this->connection->execute('INSERT INTO generated_items (quantity, price) VALUES (2, 5)');
        }
        $entity = $loaded ? GeneratedMagicItem::find(1) : new GeneratedMagicItem();

        try {
            $entity->$column = $value;
            $this->fail('Assignment to a generated column must be rejected');
        } catch (OrmException $exception) {
            $this->assertStringContainsString(sprintf('Generated column "%s"', $column), $exception->getMessage());
            $this->assertStringContainsString('read-only', $exception->getMessage());
        }

        $this->assertSame($loaded ? 10 : null, $entity->$column);
    }

    public static function generatedAssignments(): array
    {
        $cases = [];
        foreach (['total', 'stored_total'] as $column) {
            foreach ([null, 999] as $value) {
                foreach ([false, true] as $loaded) {
                    $cases[] = [$column, $value, $loaded];
                }
            }
        }

        return $cases;
    }

    private function writesSince(int $offset): array
    {
        $writes = [];
        foreach (array_slice($this->logger->getLogs(), $offset) as $entry) {
            if (preg_match('/^\s*(INSERT|UPDATE)\b/i', $entry->getStatement())) {
                $writes[] = $entry->getStatement();
            }
        }

        return $writes;
    }
}

#[Table('generated_items', 'main')]
class GeneratedMissingProperties extends Entity
{
    public ?int $id = null;
    public int $quantity;
    public int $price;
}

class GeneratedGenericItem extends GeneratedMissingProperties
{
    public ?int $total = null;
    public ?int $stored_total = null;
}

class GeneratedUninitializedItem extends GeneratedMissingProperties
{
    public int $total;
    public int $stored_total;
}

#[Table('generated_items', 'main')]
class GeneratedMagicItem extends MagicEntity
{
}
