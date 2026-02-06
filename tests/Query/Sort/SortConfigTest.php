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
        $this->assertTrue($config->isAllowed('user_name'));
        $this->assertFalse($config->isAllowed('created_at_alias'));
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

    // --- Default sort formats ---

    public function testDefaultSortStringFormat(): void
    {
        $config = new SortConfig(
            allowed: ['title', 'id'],
            default: ['title'],
        );

        $sort = $config->getDefaultSort();

        $this->assertInstanceOf(Sort::class, $sort);
        $this->assertSame('title', $sort->getColumn());
        $this->assertSame('ASC', $sort->getDir());
    }

    public function testDefaultSortStringFormatUsesDefaultDir(): void
    {
        $config = new SortConfig(
            allowed: ['title', 'id'],
            default: ['title'],
            defaultDir: 'DESC',
        );

        $sort = $config->getDefaultSort();

        $this->assertInstanceOf(Sort::class, $sort);
        $this->assertSame('DESC', $sort->getDir());
    }

    public function testDefaultSortIndexedArrayFormat(): void
    {
        $config = new SortConfig(
            allowed: ['title', 'id'],
            default: [['title', 'DESC']],
        );

        $sort = $config->getDefaultSort();

        $this->assertInstanceOf(Sort::class, $sort);
        $this->assertSame('title', $sort->getColumn());
        $this->assertSame('DESC', $sort->getDir());
    }

    public function testDefaultSortAssociativeArrayFormat(): void
    {
        $config = new SortConfig(
            allowed: ['title', 'id'],
            default: [['column' => 'title', 'dir' => 'DESC']],
        );

        $sort = $config->getDefaultSort();

        $this->assertInstanceOf(Sort::class, $sort);
        $this->assertSame('title', $sort->getColumn());
        $this->assertSame('DESC', $sort->getDir());
    }

    public function testDefaultSortMultipleReturnsMultiSort(): void
    {
        $config = new SortConfig(
            allowed: ['title', 'id'],
            default: ['title', ['id', 'DESC']],
        );

        $sort = $config->getDefaultSort();

        $this->assertInstanceOf(MultiSort::class, $sort);
        $sorts = $sort->getSorts();
        $this->assertCount(2, $sorts);
        $this->assertSame('title', $sorts[0]->getColumn());
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

        $this->assertInstanceOf(Sort::class, $sort);
        $this->assertSame('user_name', $sort->getColumn());
    }

    // --- resolve ---

    public function testResolveWithSingleItem(): void
    {
        $config = new SortConfig(
            allowed: ['title', 'created_at'],
            default: ['title'],
        );

        $sort = $config->resolve(['sort' => 'created_at:desc']);

        $this->assertInstanceOf(Sort::class, $sort);
        $this->assertSame('created_at', $sort->getColumn());
        $this->assertSame('DESC', $sort->getDir());
    }

    public function testResolveWithMultipleItems(): void
    {
        $config = new SortConfig(
            allowed: ['name', 'date'],
            default: ['name'],
        );

        $sort = $config->resolve(['sort' => ['name:asc', 'date:desc']]);

        $this->assertInstanceOf(MultiSort::class, $sort);
        $sorts = $sort->getSorts();
        $this->assertCount(2, $sorts);
        $this->assertSame('name', $sorts[0]->getColumn());
        $this->assertSame('ASC', $sorts[0]->getDir());
        $this->assertSame('date', $sorts[1]->getColumn());
        $this->assertSame('DESC', $sorts[1]->getDir());
    }

    public function testResolveWithMapping(): void
    {
        $config = new SortConfig(
            allowed: ['name' => 'user_name', 'date' => 'created_at'],
            default: ['name'],
        );

        $sort = $config->resolve(['sort' => 'date:asc']);

        $this->assertInstanceOf(Sort::class, $sort);
        $this->assertSame('created_at', $sort->getColumn());
    }

    public function testResolveWithInvalidColumnReturnsDefault(): void
    {
        $config = new SortConfig(
            allowed: ['title', 'id'],
            default: ['title'],
        );

        $sort = $config->resolve(['sort' => 'malicious_column:asc']);

        $this->assertInstanceOf(Sort::class, $sort);
        $this->assertSame('title', $sort->getColumn());
    }

    public function testResolveWithMissingParamsReturnsDefault(): void
    {
        $config = new SortConfig(
            allowed: ['title', 'id'],
            default: [['title', 'DESC']],
        );

        $sort = $config->resolve([]);

        $this->assertInstanceOf(Sort::class, $sort);
        $this->assertSame('title', $sort->getColumn());
        $this->assertSame('DESC', $sort->getDir());
    }

    public function testResolveWithoutDirUsesDefaultDir(): void
    {
        $config = new SortConfig(
            allowed: ['title'],
            default: ['title'],
            defaultDir: 'DESC',
        );

        $sort = $config->resolve(['sort' => 'title']);

        $this->assertInstanceOf(Sort::class, $sort);
        $this->assertSame('DESC', $sort->getDir());
    }

    public function testResolveNormalizesDirection(): void
    {
        $config = new SortConfig(
            allowed: ['title'],
            default: ['title'],
        );

        $sort = $config->resolve(['sort' => 'title:desc']);
        $this->assertInstanceOf(Sort::class, $sort);
        $this->assertSame('DESC', $sort->getDir());

        $sort = $config->resolve(['sort' => 'title:DESC']);
        $this->assertSame('DESC', $sort->getDir());

        $sort = $config->resolve(['sort' => 'title:asc']);
        $this->assertSame('ASC', $sort->getDir());

        $sort = $config->resolve(['sort' => 'title:invalid']);
        $this->assertSame('ASC', $sort->getDir());
    }

    public function testResolveWithCustomSortParam(): void
    {
        $config = new SortConfig(
            allowed: ['title'],
            default: ['title'],
            sortParam: 'order_by',
        );

        $sort = $config->resolve(['order_by' => 'title:desc']);

        $this->assertInstanceOf(Sort::class, $sort);
        $this->assertSame('DESC', $sort->getDir());
    }

    public function testResolveReturnsSortInterface(): void
    {
        $config = new SortConfig(
            allowed: ['title'],
            default: ['title'],
        );

        $sort = $config->resolve(['sort' => 'title:asc']);

        $this->assertInstanceOf(SortInterface::class, $sort);
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
