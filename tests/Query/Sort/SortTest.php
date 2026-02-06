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
use Hector\Query\Sort\Sort;
use PHPUnit\Framework\TestCase;

class SortTest extends TestCase
{
    private function getConnection(): Connection
    {
        return new Connection('sqlite:' . realpath(__DIR__ . '/../test.sqlite'));
    }

    public function testGetColumn(): void
    {
        $sort = new Sort('title', 'ASC');

        $this->assertSame('title', $sort->getColumn());
    }

    public function testGetDir(): void
    {
        $sort = new Sort('title', 'DESC');

        $this->assertSame('DESC', $sort->getDir());
    }

    public function testGetDirDefault(): void
    {
        $sort = new Sort('title');

        $this->assertSame('ASC', $sort->getDir());
    }

    public function testApply(): void
    {
        $builder = (new QueryBuilder($this->getConnection()))
            ->from('`table`')
            ->columns('*');

        $sort = new Sort('title', 'DESC');
        $sort->apply($builder);

        $order = $builder->order->getOrder();

        $this->assertCount(1, $order);
        $this->assertSame('title', $order[0]['column']);
        $this->assertSame('DESC', $order[0]['order']);
    }

    public function testApplyAddsToExistingOrder(): void
    {
        $builder = (new QueryBuilder($this->getConnection()))
            ->from('`table`')
            ->columns('*')
            ->orderBy('id', 'ASC');

        $sort = new Sort('title', 'DESC');
        $sort->apply($builder);

        $order = $builder->order->getOrder();

        $this->assertCount(2, $order);
        $this->assertSame('id', $order[0]['column']);
        $this->assertSame('title', $order[1]['column']);
    }
}
