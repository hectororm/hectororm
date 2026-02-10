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

use Hector\Query\Sort\MultiSort;
use Hector\Query\Sort\Sort;
use Hector\Query\Sort\SortConfig;
use Hector\Query\Sort\SortInterface;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SortConfigTest extends TestCase
{
    public function testConstructWithSimpleArray(): void
    {
        $config = new SortConfig(
            allowed: ['title', 'created_at', 'id'],
            default: ['title'],
        );

        $this->assertTrue($config->isAllowed('title'));
        $this->assertTrue($config->isAllowed('created_at'));
        $this->assertFalse($config->isAllowed('unknown'));
    }

    public function testConstructWithMappedArray(): void
    {
        $config = new SortConfig(
            allowed: ['name' => 'user_name', 'date' => 'created_at'],
            default: ['name'],
        );

        $this->assertTrue($config->isAllowed('name'));
        $this->assertTrue($config->isAllowed('date'));
        $this->assertFalse($config->isAllowed('user_name'));
    }

    public function testConstructThrowsOnInvalidDefault(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SortConfig(
            allowed: ['title', 'id'],
            default: ['unknown'],
        );
    }

    public function testConstructThrowsOnEmptyDefault(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SortConfig(
            allowed: ['title', 'id'],
            default: [],
        );
    }

    public function testConstructThrowsOnNonStringDefault(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('"column:dir" format');

        new SortConfig(
            allowed: ['title', 'id'],
            default: [['title', 'DESC']],
        );
    }

    public function testDefaultSortUsesDefaultDir(): void
    {
        $config = new SortConfig(
            allowed: ['title'],
            default: ['title'],
        );

        $sort = $config->getDefaultSort();

        $this->assertInstanceOf(Sort::class, $sort);
        $this->assertSame('title', $sort->getColumn());
        $this->assertSame('ASC', $sort->getDir());
    }

    public function testDefaultSortUsesCustomDefaultDir(): void
    {
        $config = new SortConfig(
            allowed: ['title'],
            default: ['title'],
            defaultDir: 'DESC',
        );

        $sort = $config->getDefaultSort();

        $this->assertSame('DESC', $sort->getDir());
    }

    public function testDefaultSortWithExplicitDirection(): void
    {
        $config = new SortConfig(
            allowed: ['title'],
            default: ['title:desc'],
        );

        $sort = $config->getDefaultSort();

        $this->assertSame('title', $sort->getColumn());
        $this->assertSame('DESC', $sort->getDir());
    }

    public function testDefaultSortMultiple(): void
    {
        $config = new SortConfig(
            allowed: ['title', 'id'],
            default: ['title', 'id:desc'],
        );

        $sort = $config->getDefaultSort();

        $this->assertInstanceOf(MultiSort::class, $sort);
        $sorts = $sort->getSorts();
        $this->assertCount(2, $sorts);
        $this->assertSame('title', $sorts[0]->getColumn());
        $this->assertSame('ASC', $sorts[0]->getDir());
        $this->assertSame('id', $sorts[1]->getColumn());
        $this->assertSame('DESC', $sorts[1]->getDir());
    }

    public function testDefaultSortWithMapping(): void
    {
        $config = new SortConfig(
            allowed: ['name' => 'user_name'],
            default: ['name'],
        );

        $sort = $config->getDefaultSort();

        $this->assertSame('user_name', $sort->getColumn());
    }

    public static function resolveProvider(): iterable
    {
        // [description, config args, query params, expected columns+dirs]

        // --- Free direction (mapping without :dir) ---

        yield 'free direction: user chooses desc' => [
            ['allowed' => ['title', 'created_at'], 'default' => ['title']],
            ['sort' => 'created_at:desc'],
            [['created_at', 'DESC']],
        ];

        yield 'free direction: user chooses asc' => [
            ['allowed' => ['title', 'created_at'], 'default' => ['title']],
            ['sort' => 'created_at:asc'],
            [['created_at', 'ASC']],
        ];

        yield 'free direction: no dir uses defaultDir' => [
            ['allowed' => ['title'], 'default' => ['title'], 'defaultDir' => 'DESC'],
            ['sort' => 'title'],
            [['title', 'DESC']],
        ];

        yield 'free direction: with mapping' => [
            ['allowed' => ['date' => 'created_at'], 'default' => ['date']],
            ['sort' => 'date:asc'],
            [['created_at', 'ASC']],
        ];

        // --- Locked direction (mapping with :dir) ---

        yield 'locked direction: user dir ignored' => [
            ['allowed' => ['status' => 'create_time:desc'], 'default' => ['status']],
            ['sort' => 'status:asc'],
            [['create_time', 'DESC']],
        ];

        yield 'locked direction: same dir as locked' => [
            ['allowed' => ['status' => 'create_time:desc'], 'default' => ['status']],
            ['sort' => 'status:desc'],
            [['create_time', 'DESC']],
        ];

        yield 'locked direction: no user dir' => [
            ['allowed' => ['status' => 'create_time:desc'], 'default' => ['status']],
            ['sort' => 'status'],
            [['create_time', 'DESC']],
        ];

        // --- Multi-column mapping ---

        yield 'multi-column: free + locked' => [
            ['allowed' => ['status' => ['status', 'create_time:desc']], 'default' => ['status']],
            ['sort' => 'status:asc'],
            [['status', 'ASC'], ['create_time', 'DESC']],
        ];

        yield 'multi-column: all locked, user dir ignored' => [
            ['allowed' => ['status' => ['status:asc', 'create_time:desc']], 'default' => ['status']],
            ['sort' => 'status:desc'],
            [['status', 'ASC'], ['create_time', 'DESC']],
        ];

        yield 'multi-column: all free' => [
            ['allowed' => ['status' => ['status', 'create_time']], 'default' => ['status']],
            ['sort' => 'status:desc'],
            [['status', 'DESC'], ['create_time', 'DESC']],
        ];

        // --- Multiple sort items ---

        yield 'multiple items' => [
            ['allowed' => ['name', 'date'], 'default' => ['name']],
            ['sort' => ['name:asc', 'date:desc']],
            [['name', 'ASC'], ['date', 'DESC']],
        ];

        yield 'multiple items: one invalid, one valid' => [
            ['allowed' => ['name', 'date'], 'default' => ['name']],
            ['sort' => ['unknown:asc', 'date:desc']],
            [['date', 'DESC']],
        ];

        // --- Fallback to default ---

        yield 'fallback: unknown column' => [
            ['allowed' => ['title', 'id'], 'default' => ['title']],
            ['sort' => 'malicious_column:asc'],
            [['title', 'ASC']],
        ];

        yield 'fallback: missing sort param' => [
            ['allowed' => ['title'], 'default' => ['title:desc']],
            [],
            [['title', 'DESC']],
        ];

        yield 'fallback: null sort param' => [
            ['allowed' => ['title'], 'default' => ['title:desc']],
            ['sort' => null],
            [['title', 'DESC']],
        ];

        // --- Direction normalization ---

        yield 'normalize: desc lowercase' => [
            ['allowed' => ['title'], 'default' => ['title']],
            ['sort' => 'title:desc'],
            [['title', 'DESC']],
        ];

        yield 'normalize: DESC uppercase' => [
            ['allowed' => ['title'], 'default' => ['title']],
            ['sort' => 'title:DESC'],
            [['title', 'DESC']],
        ];

        yield 'normalize: invalid direction defaults to ASC' => [
            ['allowed' => ['title'], 'default' => ['title']],
            ['sort' => 'title:invalid'],
            [['title', 'ASC']],
        ];

        // --- Custom sort param ---

        yield 'custom sort param' => [
            ['allowed' => ['title'], 'default' => ['title'], 'sortParam' => 'order_by'],
            ['order_by' => 'title:desc'],
            [['title', 'DESC']],
        ];

        // --- Full scenario ---

        yield 'full scenario: create_time:asc with free mapping' => [
            [
                'allowed' => ['create_time' => 'review_id', 'status' => ['status', 'create_time:desc']],
                'default' => ['create_time:desc'],
                'defaultDir' => 'asc',
            ],
            ['sort' => 'create_time:asc'],
            [['review_id', 'ASC']],
        ];

        yield 'full scenario: create_time:desc with locked mapping' => [
            [
                'allowed' => ['create_time' => 'review_id:desc'],
                'default' => ['create_time'],
            ],
            ['sort' => 'create_time:asc'],
            [['review_id', 'DESC']],  // Locked: ignores user asc
        ];

        yield 'full scenario: status:asc multi-column' => [
            [
                'allowed' => ['create_time' => 'review_id', 'status' => ['status', 'create_time:desc']],
                'default' => ['create_time:desc'],
                'defaultDir' => 'asc',
            ],
            ['sort' => 'status:asc'],
            [['status', 'ASC'], ['create_time', 'DESC']],
        ];

        yield 'full scenario: status without dir uses defaultDir' => [
            [
                'allowed' => ['create_time' => 'review_id', 'status' => ['status', 'create_time:desc']],
                'default' => ['create_time:desc'],
                'defaultDir' => 'asc',
            ],
            ['sort' => 'status'],
            [['status', 'ASC'], ['create_time', 'DESC']],
        ];

        yield 'full scenario: unknown falls back to default' => [
            [
                'allowed' => ['create_time' => 'review_id', 'status' => ['status', 'create_time:desc']],
                'default' => ['create_time:desc'],
                'defaultDir' => 'asc',
            ],
            ['sort' => 'unknown'],
            [['review_id', 'DESC']],
        ];
    }

    /**
     * @dataProvider resolveProvider
     *
     * @param array $configArgs SortConfig constructor args (allowed, default, defaultDir?, sortParam?)
     * @param array $params Query parameters to resolve
     * @param array $expected Expected result as [[column, dir], ...]
     */
    public function testResolve(array $configArgs, array $params, array $expected): void
    {
        $config = new SortConfig(
            allowed: $configArgs['allowed'],
            default: $configArgs['default'],
            defaultDir: $configArgs['defaultDir'] ?? 'ASC',
            sortParam: $configArgs['sortParam'] ?? 'sort',
        );

        $result = $config->resolve($params);

        $this->assertInstanceOf(SortInterface::class, $result);

        if (count($expected) === 1) {
            $this->assertInstanceOf(Sort::class, $result);
            $this->assertSame($expected[0][0], $result->getColumn());
            $this->assertSame($expected[0][1], $result->getDir());
        } else {
            $this->assertInstanceOf(MultiSort::class, $result);
            $sorts = $result->getSorts();
            $this->assertCount(count($expected), $sorts);

            foreach ($expected as $i => [$expectedColumn, $expectedDir]) {
                $this->assertSame($expectedColumn, $sorts[$i]->getColumn(), "Column mismatch at index $i");
                $this->assertSame($expectedDir, $sorts[$i]->getDir(), "Direction mismatch at index $i");
            }
        }
    }

    // --- Accessors ---

    public function testGetSortParam(): void
    {
        $config = new SortConfig(
            allowed: ['title'],
            default: ['title'],
            sortParam: 'order_by',
        );

        $this->assertSame('order_by', $config->getSortParam());
    }

    public function testGetSortParamDefault(): void
    {
        $config = new SortConfig(
            allowed: ['title'],
            default: ['title'],
        );

        $this->assertSame('sort', $config->getSortParam());
    }
}
