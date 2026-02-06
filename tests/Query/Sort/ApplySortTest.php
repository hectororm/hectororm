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

namespace Hector\Query\Tests\Sort;

use Hector\Connection\Connection;
use Hector\Query\QueryBuilder;
use Hector\Query\Sort\MultiSort;
use Hector\Query\Sort\Sort;
use Hector\Query\Sort\SortConfig;
use PHPUnit\Framework\TestCase;

class ApplySortTest extends TestCase
{
    private function getConnection(): Connection
    {
        return new Connection('sqlite:' . realpath(__DIR__ . '/../test.sqlite'));
    }

    public function testApplySortIsAdditive(): void
    {
        $builder = (new QueryBuilder($this->getConnection()))
            ->from('`table`')
            ->columns('*')
            ->orderBy('id', 'ASC');

        $sort = new Sort('title', 'DESC');
        $builder->applySort($sort);

        $order = $builder->order->getOrder();

        $this->assertCount(2, $order);
        $this->assertSame('id', $order[0]['column']);
        $this->assertSame('title', $order[1]['column']);
        $this->assertSame('DESC', $order[1]['order']);
    }

    public function testApplySortOnEmptyBuilder(): void
    {
        $builder = (new QueryBuilder($this->getConnection()))
            ->from('`table`')
            ->columns('*');

        $sort = new Sort('title', 'DESC');
        $builder->applySort($sort);

        $order = $builder->order->getOrder();

        $this->assertCount(1, $order);
        $this->assertSame('title', $order[0]['column']);
        $this->assertSame('DESC', $order[0]['order']);
    }

    public function testResetOrderThenApplySort(): void
    {
        $builder = (new QueryBuilder($this->getConnection()))
            ->from('`table`')
            ->columns('*')
            ->orderBy('old_column', 'ASC');

        $sort = new Sort('title', 'DESC');
        $builder->resetOrder()->applySort($sort);

        $order = $builder->order->getOrder();

        $this->assertCount(1, $order);
        $this->assertSame('title', $order[0]['column']);
        $this->assertSame('DESC', $order[0]['order']);
    }

    public function testApplySortWithMultiSort(): void
    {
        $builder = (new QueryBuilder($this->getConnection()))
            ->from('`table`')
            ->columns('*');

        $sort = new MultiSort(
            new Sort('title', 'ASC'),
            new Sort('id', 'DESC'),
        );
        $builder->applySort($sort);

        $order = $builder->order->getOrder();

        $this->assertCount(2, $order);
        $this->assertSame('title', $order[0]['column']);
        $this->assertSame('ASC', $order[0]['order']);
        $this->assertSame('id', $order[1]['column']);
        $this->assertSame('DESC', $order[1]['order']);
    }

    public function testApplySortReturnsSelf(): void
    {
        $builder = (new QueryBuilder($this->getConnection()))
            ->from('`table`')
            ->columns('*');

        $result = $builder->applySort(new Sort('title', 'ASC'));

        $this->assertSame($builder, $result);
    }

    public function testApplySortWithSortConfig(): void
    {
        $config = new SortConfig(
            allowed: ['title', 'created_at', 'id'],
            default: ['title'],
        );

        $builder = (new QueryBuilder($this->getConnection()))
            ->from('`table`')
            ->columns('*');

        $sort = $config->resolve(['sort' => 'created_at:desc']);
        $builder->applySort($sort);

        $order = $builder->order->getOrder();

        $this->assertCount(1, $order);
        $this->assertSame('created_at', $order[0]['column']);
        $this->assertSame('DESC', $order[0]['order']);
    }

    public function testApplySortWithSortConfigDefault(): void
    {
        $config = new SortConfig(
            allowed: ['title', 'id'],
            default: ['title'],
        );

        $builder = (new QueryBuilder($this->getConnection()))
            ->from('`table`')
            ->columns('*');

        $sort = $config->resolve([]);
        $builder->applySort($sort);

        $order = $builder->order->getOrder();

        $this->assertCount(1, $order);
        $this->assertSame('title', $order[0]['column']);
        $this->assertSame('ASC', $order[0]['order']);
    }
}
