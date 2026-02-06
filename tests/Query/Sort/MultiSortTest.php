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
use PHPUnit\Framework\TestCase;

class MultiSortTest extends TestCase
{
    private function getConnection(): Connection
    {
        return new Connection('sqlite:' . realpath(__DIR__ . '/../test.sqlite'));
    }

    public function testGetSorts(): void
    {
        $sort1 = new Sort('title', 'ASC');
        $sort2 = new Sort('id', 'DESC');
        $multi = new MultiSort($sort1, $sort2);

        $sorts = $multi->getSorts();

        $this->assertCount(2, $sorts);
        $this->assertSame($sort1, $sorts[0]);
        $this->assertSame($sort2, $sorts[1]);
    }

    public function testApply(): void
    {
        $builder = (new QueryBuilder($this->getConnection()))
            ->from('`table`')
            ->columns('*');

        $multi = new MultiSort(
            new Sort('title', 'ASC'),
            new Sort('id', 'DESC'),
        );
        $multi->apply($builder);

        $order = $builder->order->getOrder();

        $this->assertCount(2, $order);
        $this->assertSame('title', $order[0]['column']);
        $this->assertSame('ASC', $order[0]['order']);
        $this->assertSame('id', $order[1]['column']);
        $this->assertSame('DESC', $order[1]['order']);
    }

    public function testApplyEmpty(): void
    {
        $builder = (new QueryBuilder($this->getConnection()))
            ->from('`table`')
            ->columns('*');

        $multi = new MultiSort();
        $multi->apply($builder);

        $order = $builder->order->getOrder();

        $this->assertCount(0, $order);
    }
}
