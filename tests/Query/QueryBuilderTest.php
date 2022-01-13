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

use Hector\Connection\Connection;
use Hector\Query\Delete;
use Hector\Query\Insert;
use Hector\Query\QueryBuilder;
use Hector\Query\Select;
use Hector\Query\Update;
use PHPUnit\Framework\TestCase;

class QueryBuilderTest extends TestCase
{
    private ?Connection $connection = null;

    /**
     * Get connection.
     *
     * @return Connection
     */
    private function getConnection(): Connection
    {
        if (null !== $this->connection) {
            return $this->connection;
        }

        return $this->connection = new Connection('sqlite:' . realpath(__DIR__ . '/test.sqlite'));
    }

    public function testFetchOne()
    {
        $queryBuilder = new QueryBuilder($this->getConnection());

        $result = $queryBuilder->from('`table`')->columns('*')->fetchOne();

        $this->assertNotNull($result);
        $this->assertArrayHasKey('table_id', $result);
    }

    public function testFetchAll()
    {
        $queryBuilder = new QueryBuilder($this->getConnection());

        $result = $queryBuilder->from('`table`')->columns('*')->fetchAll();

        $this->assertInstanceOf(\Generator::class, $result);
        $this->assertIsIterable($result);

        $result = iterator_to_array($result);
        $this->assertGreaterThanOrEqual(2, $result);
    }

    public function testFetchColumn()
    {
        $queryBuilder = new QueryBuilder($this->getConnection());

        $result = $queryBuilder->from('`table`')->fetchColumn(1);

        $this->assertInstanceOf(\Generator::class, $result);
        $this->assertIsIterable($result);

        $result = iterator_to_array($result);
        $this->assertGreaterThanOrEqual(2, $result);
        $this->assertNotNull($result);
        $this->assertContains("Foo", $result);
        $this->assertContains("Bar", $result);
    }

    public function testSelect()
    {
        $reflectionMethod = new \ReflectionMethod(QueryBuilder::class, 'makeSelect');
        $reflectionMethod->setAccessible(true);

        $queryBuilder = new QueryBuilder($this->getConnection());
        $queryBuilder = $queryBuilder->select('foo', 'f');
        $binding = [];

        $this->assertInstanceOf(QueryBuilder::class, $queryBuilder);
        $this->assertEquals(
            'SELECT * FROM foo AS f',
            $reflectionMethod->invoke($queryBuilder)->getStatement($binding)
        );
        $this->assertEmpty($binding);
    }

    public function testDistinct()
    {
        $queryBuilder = new FakeQueryBuilder($this->getConnection());
        $queryBuilder = $queryBuilder->from('foo', 'f')->orderBy('bar', 'DESC')->distinct();
        $binding = [];
        $select = $queryBuilder->makeSelect();

        $this->assertInstanceOf(Select::class, $select);
        $this->assertInstanceOf(QueryBuilder::class, $queryBuilder);
        $this->assertEquals(
            'SELECT DISTINCT * FROM foo AS f ORDER BY bar DESC',
            $select->getStatement($binding)
        );
        $this->assertEmpty($binding);
    }

    public function testSelectWithClosureCondition()
    {
        $reflectionMethod = new \ReflectionMethod(QueryBuilder::class, 'makeSelect');
        $reflectionMethod->setAccessible(true);

        $queryBuilder = new QueryBuilder($this->getConnection());
        $queryBuilder->from('`foo`')
            ->where(
                function ($query) {
                    $query->where('bar', 'baz');
                }
            );

        $binding = [];
        $this->assertEquals(
            'SELECT * FROM `foo` WHERE ( bar = ? )',
            $reflectionMethod->invoke($queryBuilder)->getStatement($binding)
        );
        $this->assertEquals(['baz'], $binding);

        $binding = [];
        $this->assertEquals(
            'SELECT * FROM `foo` WHERE ( bar = ? )',
            $reflectionMethod->invoke($queryBuilder)->getStatement($binding)
        );
        $this->assertEquals(['baz'], $binding);
    }

    public function testSelectWithClosureConditionWithReturnedValue()
    {
        $reflectionMethod = new \ReflectionMethod(QueryBuilder::class, 'makeSelect');
        $reflectionMethod->setAccessible(true);

        $queryBuilder = new QueryBuilder($this->getConnection());
        $queryBuilder->from('`foo`')
            ->where(
                function () {
                    return 'bar';
                },
                'test'
            );

        $binding = [];
        $this->assertEquals(
            'SELECT * FROM `foo` WHERE bar = ?',
            $reflectionMethod->invoke($queryBuilder)->getStatement($binding)
        );
        $this->assertEquals(['test'], $binding);
    }

    public function testSelectWithClosureConditionValue()
    {
        $reflectionMethod = new \ReflectionMethod(QueryBuilder::class, 'makeSelect');
        $reflectionMethod->setAccessible(true);

        $queryBuilder = new QueryBuilder($this->getConnection());
        $queryBuilder->from('`foo`')
            ->where(
                'bar',
                function () {
                    return 'test';
                }
            );

        $binding = [];
        $this->assertEquals(
            'SELECT * FROM `foo` WHERE bar = ?',
            $reflectionMethod->invoke($queryBuilder)->getStatement($binding)
        );
        $this->assertEquals(['test'], $binding);
    }

    public function testMakeSelect()
    {
        $queryBuilder = new FakeQueryBuilder($this->getConnection());
        $queryBuilder = $queryBuilder->from('foo', 'f')->orderBy('bar', 'DESC');
        $binding = [];
        $select = $queryBuilder->makeSelect();

        $this->assertInstanceOf(Select::class, $select);
        $this->assertInstanceOf(QueryBuilder::class, $queryBuilder);
        $this->assertEquals(
            'SELECT * FROM foo AS f ORDER BY bar DESC',
            $select->getStatement($binding)
        );
        $this->assertEmpty($binding);
    }

    public function testMakeCount()
    {
        $queryBuilder = new FakeQueryBuilder($this->getConnection());
        $queryBuilder = $queryBuilder
            ->column('f.bar')
            ->from('foo', 'f')
            ->orderBy('bar', 'DESC')
            ->limit(2);
        $binding = [];
        $select = $queryBuilder->makeCount();

        $this->assertInstanceOf(Select::class, $select);
        $this->assertInstanceOf(QueryBuilder::class, $queryBuilder);
        $this->assertEquals(
            'SELECT COUNT(*) AS `count` FROM ( SELECT 1 FROM foo AS f ) AS countable',
            $select->getStatement($binding)
        );
        $this->assertEmpty($binding);
    }

    public function testMakeCount_withDistinct()
    {
        $queryBuilder = new FakeQueryBuilder($this->getConnection());
        $queryBuilder = $queryBuilder
            ->column('f.bar')
            ->from('foo', 'f')
            ->orderBy('bar', 'DESC')
            ->limit(2);
        $binding = [];

        $select = $queryBuilder->makeCount();
        $this->assertEquals(
            'SELECT COUNT(*) AS `count` FROM ( SELECT 1 FROM foo AS f ) AS countable',
            $select->getStatement($binding)
        );
        $this->assertEmpty($binding);

        $queryBuilder->distinct();
        $select = $queryBuilder->makeCount();
        $this->assertEquals(
            'SELECT COUNT(*) AS `count` FROM ( SELECT DISTINCT f.bar FROM foo AS f ) AS countable',
            $select->getStatement($binding)
        );
        $this->assertEmpty($binding);
    }

    public function testMakeCount_withHaving()
    {
        $queryBuilder = new FakeQueryBuilder($this->getConnection());
        $queryBuilder = $queryBuilder
            ->column('f.bar')
            ->from('foo', 'f')
            ->orderBy('bar', 'DESC')
            ->having('f.bar = 1')
            ->limit(2);
        $binding = [];
        $select = $queryBuilder->makeCount();

        $this->assertInstanceOf(Select::class, $select);
        $this->assertInstanceOf(QueryBuilder::class, $queryBuilder);
        $this->assertEquals(
            'SELECT COUNT(*) AS `count` FROM ( SELECT f.bar FROM foo AS f HAVING f.bar = 1 ) AS countable',
            $select->getStatement($binding)
        );
        $this->assertEmpty($binding);
    }

    public function testMakeCount_withGroup()
    {
        $queryBuilder = new FakeQueryBuilder($this->getConnection());
        $queryBuilder = $queryBuilder
            ->from('foo', 'f')
            ->orderBy('bar', 'DESC')
            ->groupBy('foo');
        $binding = [];
        $select = $queryBuilder->makeCount();

        $this->assertInstanceOf(Select::class, $select);
        $this->assertInstanceOf(QueryBuilder::class, $queryBuilder);
        $this->assertEquals(
            'SELECT COUNT(*) AS `count` FROM ( SELECT 1 FROM foo AS f GROUP BY foo ) AS countable',
            $select->getStatement($binding)
        );
        $this->assertEmpty($binding);
    }

    public function testMakeExists()
    {
        $queryBuilder = new FakeQueryBuilder($this->getConnection());
        $queryBuilder->from('foo', 'f');
        $queryBuilder->where('f.bar', 'baz');

        $binding = [];
        $select = $queryBuilder->makeExists();

        $this->assertInstanceOf(Select::class, $select);
        $this->assertEquals(
            'SELECT EXISTS( SELECT 1 FROM foo AS f WHERE f.bar = ? ) AS `exists`',
            $select->getStatement($binding)
        );
        $this->assertEquals(['baz'], $binding);
    }

    public function testMakeInsert()
    {
        $queryBuilder = new FakeQueryBuilder($this->getConnection());
        $queryBuilder->from('foo', 'f');
        $queryBuilder->assign('bar', 'bar_value');

        $binding = [];
        $insert = $queryBuilder->makeInsert();

        $this->assertInstanceOf(Insert::class, $insert);
        $this->assertEquals(
            'INSERT INTO foo SET bar = ?',
            $insert->getStatement($binding)
        );
        $this->assertEquals(['bar_value'], $binding);
    }

    public function testMakeUpdate()
    {
        $queryBuilder = new FakeQueryBuilder($this->getConnection());
        $queryBuilder->from('foo', 'f');
        $queryBuilder->assign('bar', 'bar_value');

        $binding = [];
        $update = $queryBuilder->makeUpdate();

        $this->assertInstanceOf(Update::class, $update);
        $this->assertEquals(
            'UPDATE foo AS f SET bar = ?',
            $update->getStatement($binding)
        );
        $this->assertEquals(['bar_value'], $binding);
    }

    public function testDelete()
    {
        $queryBuilder = new FakeQueryBuilder($this->getConnection());
        $queryBuilder->from('foo', 'f');
        $queryBuilder->where('bar', 'bar_value');

        $binding = [];
        $delete = $queryBuilder->makeDelete();

        $this->assertInstanceOf(Delete::class, $delete);
        $this->assertEquals(
            'DELETE FROM foo WHERE bar = ?',
            $delete->getStatement($binding)
        );
        $this->assertEquals(['bar_value'], $binding);
    }
}
