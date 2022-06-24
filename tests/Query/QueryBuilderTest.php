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
        $binds = new BindParamList();

        $this->assertInstanceOf(QueryBuilder::class, $queryBuilder);
        $this->assertEquals(
            'SELECT * FROM foo AS f',
            $reflectionMethod->invoke($queryBuilder)->getStatement($binds)
        );
        $this->assertEmpty($binds);
    }

    public function testDistinct()
    {
        $queryBuilder = new FakeQueryBuilder($this->getConnection());
        $queryBuilder = $queryBuilder->from('foo', 'f')->orderBy('bar', 'DESC')->distinct();
        $binds = new BindParamList();
        $select = $queryBuilder->makeSelect();

        $this->assertInstanceOf(Select::class, $select);
        $this->assertInstanceOf(QueryBuilder::class, $queryBuilder);
        $this->assertEquals(
            'SELECT DISTINCT * FROM foo AS f ORDER BY bar DESC',
            $select->getStatement($binds)
        );
        $this->assertEmpty($binds);
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

        $binds = new BindParamList();
        $this->assertEquals(
            'SELECT * FROM `foo` WHERE ( bar = :_h_0 )',
            $reflectionMethod->invoke($queryBuilder)->getStatement($binds)
        );
        $this->assertEquals(
            ['_h_0' => 'baz'],
            array_map(fn(BindParam $bind) => $bind->getValue(), $binds->getArrayCopy())
        );

        $binds = new BindParamList();
        $this->assertEquals(
            'SELECT * FROM `foo` WHERE ( bar = :_h_0 )',
            $reflectionMethod->invoke($queryBuilder)->getStatement($binds)
        );
        $this->assertEquals(
            ['_h_0' => 'baz'],
            array_map(fn(BindParam $bind) => $bind->getValue(), $binds->getArrayCopy())
        );
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

        $binds = new BindParamList();
        $this->assertEquals(
            'SELECT * FROM `foo` WHERE bar = :_h_0',
            $reflectionMethod->invoke($queryBuilder)->getStatement($binds)
        );
        $this->assertEquals(
            ['_h_0' => 'test'],
            array_map(fn(BindParam $bind) => $bind->getValue(), $binds->getArrayCopy())
        );
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

        $binds = new BindParamList();
        $this->assertEquals(
            'SELECT * FROM `foo` WHERE bar = :_h_0',
            $reflectionMethod->invoke($queryBuilder)->getStatement($binds)
        );
        $this->assertEquals(
            ['_h_0' => 'test'],
            array_map(fn(BindParam $bind) => $bind->getValue(), $binds->getArrayCopy())
        );
    }

    public function testMakeSelect()
    {
        $queryBuilder = new FakeQueryBuilder($this->getConnection());
        $queryBuilder = $queryBuilder->from('foo', 'f')->orderBy('bar', 'DESC');
        $binds = new BindParamList();
        $select = $queryBuilder->makeSelect();

        $this->assertInstanceOf(Select::class, $select);
        $this->assertInstanceOf(QueryBuilder::class, $queryBuilder);
        $this->assertEquals(
            'SELECT * FROM foo AS f ORDER BY bar DESC',
            $select->getStatement($binds)
        );
        $this->assertEmpty($binds);
    }

    public function testMakeCount()
    {
        $queryBuilder = new FakeQueryBuilder($this->getConnection());
        $queryBuilder = $queryBuilder
            ->column('f.bar')
            ->from('foo', 'f')
            ->orderBy('bar', 'DESC')
            ->limit(2);
        $binds = new BindParamList();
        $select = $queryBuilder->makeCount();

        $this->assertInstanceOf(Select::class, $select);
        $this->assertInstanceOf(QueryBuilder::class, $queryBuilder);
        $this->assertEquals(
            'SELECT COUNT(*) AS `count` FROM ( SELECT 1 FROM foo AS f ) AS countable',
            $select->getStatement($binds)
        );
        $this->assertEmpty($binds);
    }

    public function testMakeCount_withDistinct()
    {
        $queryBuilder = new FakeQueryBuilder($this->getConnection());
        $queryBuilder = $queryBuilder
            ->column('f.bar')
            ->from('foo', 'f')
            ->orderBy('bar', 'DESC')
            ->limit(2);
        $binds = new BindParamList();

        $select = $queryBuilder->makeCount();
        $this->assertEquals(
            'SELECT COUNT(*) AS `count` FROM ( SELECT 1 FROM foo AS f ) AS countable',
            $select->getStatement($binds)
        );
        $this->assertEmpty($binds);

        $queryBuilder->distinct();
        $select = $queryBuilder->makeCount();
        $this->assertEquals(
            'SELECT COUNT(*) AS `count` FROM ( SELECT DISTINCT f.bar FROM foo AS f ) AS countable',
            $select->getStatement($binds)
        );
        $this->assertEmpty($binds);
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
        $binds = new BindParamList();
        $select = $queryBuilder->makeCount();

        $this->assertInstanceOf(Select::class, $select);
        $this->assertInstanceOf(QueryBuilder::class, $queryBuilder);
        $this->assertEquals(
            'SELECT COUNT(*) AS `count` FROM ( SELECT f.bar FROM foo AS f HAVING f.bar = 1 ) AS countable',
            $select->getStatement($binds)
        );
        $this->assertEmpty($binds);
    }

    public function testMakeCount_withGroup()
    {
        $queryBuilder = new FakeQueryBuilder($this->getConnection());
        $queryBuilder = $queryBuilder
            ->from('foo', 'f')
            ->orderBy('bar', 'DESC')
            ->groupBy('foo');
        $binds = new BindParamList();
        $select = $queryBuilder->makeCount();

        $this->assertInstanceOf(Select::class, $select);
        $this->assertInstanceOf(QueryBuilder::class, $queryBuilder);
        $this->assertEquals(
            'SELECT COUNT(*) AS `count` FROM ( SELECT 1 FROM foo AS f GROUP BY foo ) AS countable',
            $select->getStatement($binds)
        );
        $this->assertEmpty($binds);
    }

    public function testMakeExists()
    {
        $queryBuilder = new FakeQueryBuilder($this->getConnection());
        $queryBuilder->from('foo', 'f');
        $queryBuilder->where('f.bar', 'baz');

        $binds = new BindParamList();
        $select = $queryBuilder->makeExists();

        $this->assertInstanceOf(Select::class, $select);
        $this->assertEquals(
            'SELECT EXISTS( SELECT 1 FROM foo AS f WHERE f.bar = :_h_0 ) AS `exists`',
            $select->getStatement($binds)
        );
        $this->assertEquals(
            ['_h_0' => 'baz'],
            array_map(fn(BindParam $bind) => $bind->getValue(), $binds->getArrayCopy())
        );
    }

    public function testMakeInsert()
    {
        $queryBuilder = new FakeQueryBuilder($this->getConnection());
        $queryBuilder->from('foo', 'f');
        $queryBuilder->assign('bar', 'bar_value');

        $binds = new BindParamList();
        $insert = $queryBuilder->makeInsert();

        $this->assertInstanceOf(Insert::class, $insert);
        $this->assertEquals(
            'INSERT INTO foo SET bar = :_h_0',
            $insert->getStatement($binds)
        );
        $this->assertEquals(
            ['_h_0' => 'bar_value'],
            array_map(fn(BindParam $bind) => $bind->getValue(), $binds->getArrayCopy())
        );
    }

    public function testMakeUpdate()
    {
        $queryBuilder = new FakeQueryBuilder($this->getConnection());
        $queryBuilder->from('foo', 'f');
        $queryBuilder->assign('bar', 'bar_value');

        $binds = new BindParamList();
        $update = $queryBuilder->makeUpdate();

        $this->assertInstanceOf(Update::class, $update);
        $this->assertEquals(
            'UPDATE foo AS f SET bar = :_h_0',
            $update->getStatement($binds)
        );
        $this->assertEquals(
            ['_h_0' => 'bar_value'],
            array_map(fn(BindParam $bind) => $bind->getValue(), $binds->getArrayCopy())
        );
    }

    public function testDelete()
    {
        $queryBuilder = new FakeQueryBuilder($this->getConnection());
        $queryBuilder->from('foo', 'f');
        $queryBuilder->where('bar', 'bar_value');

        $binds = new BindParamList();
        $delete = $queryBuilder->makeDelete();

        $this->assertInstanceOf(Delete::class, $delete);
        $this->assertEquals(
            'DELETE FROM foo WHERE bar = :_h_0',
            $delete->getStatement($binds)
        );
        $this->assertEquals(
            ['_h_0' => 'bar_value'],
            array_map(fn(BindParam $bind) => $bind->getValue(), $binds->getArrayCopy())
        );
    }
}
