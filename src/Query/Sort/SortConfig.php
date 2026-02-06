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

namespace Hector\Query\Sort;

use Hector\Query\Component\Order;
use InvalidArgumentException;

final class SortConfig
{
    /** @var array<string, string> Allowed columns mapped to their real column names */
    private array $allowedColumns;

    /** @var SortInterface Normalized default sort */
    private SortInterface $defaultSort;

    /**
     * @param array<string, string>|array<string> $allowed Allowed sort columns (key = param name, value = real column)
     * @param array $default Default sort columns, each element is either:
     *                       - a string: column name (uses defaultDir)
     *                       - an indexed array: [column, direction]
     *                       - an associative array: ['column' => ..., 'dir' => ...]
     * @param string $defaultDir Default direction when direction is omitted
     * @param string $sortParam Query parameter name for sort (e.g. ?sort=name:asc)
     */
    public function __construct(
        array $allowed,
        array $default,
        private string $defaultDir = Order::ORDER_ASC,
        private string $sortParam = 'sort',
    ) {
        $this->allowedColumns = $this->normalizeAllowed($allowed);
        $this->defaultDir = $this->normalizeDirection($defaultDir);
        $this->defaultSort = $this->normalizeDefault($default);
    }

    /**
     * Normalize allowed columns array.
     *
     * @param array<string, string>|array<string> $allowed
     *
     * @return array<string, string>
     */
    private function normalizeAllowed(array $allowed): array
    {
        $normalized = [];

        foreach ($allowed as $key => $value) {
            if (is_int($key)) {
                $normalized[$value] = $value;
            } else {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }

    /**
     * Normalize direction.
     */
    private function normalizeDirection(string $dir): string
    {
        $dir = strtoupper(trim($dir));

        return $dir === Order::ORDER_DESC ? Order::ORDER_DESC : Order::ORDER_ASC;
    }

    /**
     * Normalize default sort array.
     *
     * @param array $default
     *
     * @return SortInterface
     */
    private function normalizeDefault(array $default): SortInterface
    {
        $sorts = [];

        foreach ($default as $item) {
            if (is_string($item)) {
                // 'title' -> uses defaultDir
                $sorts[] = new Sort(
                    $this->resolveColumn($item),
                    $this->defaultDir,
                );
            } elseif (is_array($item) && isset($item['column'])) {
                // ['column' => 'title', 'dir' => 'DESC']
                $sorts[] = new Sort(
                    $this->resolveColumn($item['column']),
                    $this->normalizeDirection($item['dir'] ?? $this->defaultDir),
                );
            } elseif (is_array($item) && isset($item[0])) {
                // ['title', 'DESC']
                $sorts[] = new Sort(
                    $this->resolveColumn($item[0]),
                    $this->normalizeDirection($item[1] ?? $this->defaultDir),
                );
            } else {
                throw new InvalidArgumentException(
                    'Invalid default sort item format'
                );
            }
        }

        if (empty($sorts)) {
            throw new InvalidArgumentException('Default sort must not be empty');
        }

        if (count($sorts) === 1) {
            return $sorts[0];
        }

        return new MultiSort(...$sorts);
    }

    /**
     * Resolve column name through allowed mapping.
     *
     * @throws InvalidArgumentException If column is not in allowed list
     */
    private function resolveColumn(string $column): string
    {
        // Direct match as param name
        if (isset($this->allowedColumns[$column])) {
            return $this->allowedColumns[$column];
        }

        // Direct match as real column name
        if (in_array($column, $this->allowedColumns, true)) {
            return $column;
        }

        throw new InvalidArgumentException(
            sprintf('Default sort column "%s" is not in allowed list', $column)
        );
    }

    /**
     * Get sort param name.
     */
    public function getSortParam(): string
    {
        return $this->sortParam;
    }

    /**
     * Get default sort.
     */
    public function getDefaultSort(): SortInterface
    {
        return $this->defaultSort;
    }

    /**
     * Resolve sort from request parameters.
     *
     * Accepts:
     * - ?sort=name:asc (single)
     * - ?sort[0]=name:asc&sort[1]=enable:desc (multi)
     *
     * @param array<string, mixed> $params Query parameters
     */
    public function resolve(array $params): SortInterface
    {
        $sortParam = $params[$this->sortParam] ?? null;

        if (null === $sortParam) {
            return $this->getDefaultSort();
        }

        $items = is_array($sortParam) ? $sortParam : [$sortParam];
        $sorts = [];

        foreach ($items as $item) {
            if (!is_string($item)) {
                continue;
            }

            $parsed = $this->parseSortItem($item);

            if (null === $parsed) {
                continue;
            }

            $sorts[] = new Sort($parsed[0], $parsed[1]);
        }

        if (empty($sorts)) {
            return $this->getDefaultSort();
        }

        if (count($sorts) === 1) {
            return $sorts[0];
        }

        return new MultiSort(...$sorts);
    }

    /**
     * Parse a sort item string "column:dir".
     *
     * @return array{0: string, 1: string}|null [column, direction] or null if invalid
     */
    private function parseSortItem(string $item): ?array
    {
        $parts = explode(':', $item, 2);
        $column = trim($parts[0]);

        if ('' === $column || !isset($this->allowedColumns[$column])) {
            return null;
        }

        $dir = isset($parts[1]) ? $this->normalizeDirection($parts[1]) : $this->defaultDir;

        return [$this->allowedColumns[$column], $dir];
    }

    /**
     * Check if a column is allowed.
     */
    public function isAllowed(string $column): bool
    {
        return isset($this->allowedColumns[$column]) || in_array($column, $this->allowedColumns, true);
    }
}
