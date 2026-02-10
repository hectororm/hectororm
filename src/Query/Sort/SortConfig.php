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
    /** @var array<string, string|array<string>> Allowed sorts: key = alias, value = real column(s) */
    private array $allowed;

    /** @var SortInterface Normalized default sort */
    private SortInterface $defaultSort;

    /**
     * @param array<string, string|array<string>>|array<string> $allowed Allowed sort columns.
     *   Keys are user-facing alias names (without direction).
     *   Values define the real column mapping(s) in "column" or "column:dir" format:
     *   - "column" (no dir): direction is free, user can choose (or defaultDir applies)
     *   - "column:dir" (with dir): direction is locked, user cannot override it
     *   - array of strings: multi-column mapping, same rules per item
     *   Numeric keys: value is used as both alias and column (e.g. ['title', 'id']).
     * @param array<string> $default Default sort in "column:dir" format (e.g. ['create_time:desc']).
     *   Column names must match an alias in $allowed.
     * @param string $defaultDir Default direction when not specified
     * @param string $sortParam Query parameter name for sort (e.g. ?sort=name:asc)
     */
    public function __construct(
        array $allowed,
        array $default,
        private string $defaultDir = Order::ORDER_ASC,
        private string $sortParam = 'sort',
    ) {
        $this->defaultDir = $this->normalizeDirection($defaultDir);
        $this->allowed = $this->normalizeAllowed($allowed);
        $this->defaultSort = $this->normalizeDefault($default);
    }

    /**
     * Normalize allowed array.
     *
     * @return array<string, string|array<string>>
     */
    private function normalizeAllowed(array $allowed): array
    {
        $normalized = [];

        foreach ($allowed as $key => $value) {
            if (is_int($key)) {
                if (!is_string($value)) {
                    throw new InvalidArgumentException(
                        'Allowed sort items with numeric keys must be strings'
                    );
                }
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
     * Parse a "column" or "column:dir" spec.
     *
     * @return array{column: string, dir: ?string} dir is null if not specified
     */
    private function parseSpec(string $spec): array
    {
        $parts = explode(':', $spec, 2);
        $column = trim($parts[0]);
        $dir = isset($parts[1]) ? $this->normalizeDirection($parts[1]) : null;

        return ['column' => $column, 'dir' => $dir];
    }

    /**
     * Normalize default sort array.
     *
     * @param array<string> $default Each item in "alias:dir" format
     *
     * @return SortInterface
     */
    private function normalizeDefault(array $default): SortInterface
    {
        if (empty($default)) {
            throw new InvalidArgumentException('Default sort must not be empty');
        }

        $sorts = [];

        foreach ($default as $item) {
            if (!is_string($item)) {
                throw new InvalidArgumentException(
                    'Default sort items must be strings in "column:dir" format'
                );
            }

            $parsed = $this->parseSpec($item);
            $alias = $parsed['column'];
            $requestedDir = $parsed['dir'] ?? $this->defaultDir;

            if (!isset($this->allowed[$alias])) {
                throw new InvalidArgumentException(
                    sprintf('Default sort column "%s" is not in allowed list', $alias)
                );
            }

            $resolved = $this->resolveMapping($this->allowed[$alias], $requestedDir);

            if ($resolved instanceof MultiSort) {
                foreach ($resolved->getSorts() as $sort) {
                    $sorts[] = $sort;
                }
            } else {
                $sorts[] = $resolved;
            }
        }

        if (count($sorts) === 1) {
            return $sorts[0];
        }

        return new MultiSort(...$sorts);
    }

    /**
     * Resolve a mapping value into a SortInterface.
     *
     * For each spec in the mapping:
     * - "column:dir" (dir present) → direction is locked, requestedDir is ignored
     * - "column" (no dir) → direction comes from requestedDir
     *
     * @param string|array<string> $mapping The mapped value(s)
     * @param string $requestedDir Direction requested by the user (or defaultDir)
     */
    private function resolveMapping(string|array $mapping, string $requestedDir): SortInterface
    {
        if (is_string($mapping)) {
            $parsed = $this->parseSpec($mapping);
            return new Sort($parsed['column'], $parsed['dir'] ?? $requestedDir);
        }

        $sorts = [];

        foreach ($mapping as $spec) {
            $parsed = $this->parseSpec($spec);
            $sorts[] = new Sort($parsed['column'], $parsed['dir'] ?? $requestedDir);
        }

        if (count($sorts) === 1) {
            return $sorts[0];
        }

        return new MultiSort(...$sorts);
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
     * - ?sort[0]=name:asc&sort[1]=status:desc (multi)
     *
     * The user sends "alias:dir". The alias must match a key in allowed.
     * The user direction is applied only to mapping specs without a locked direction.
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

            $parsed = $this->parseSpec($item);
            $alias = $parsed['column'];
            $requestedDir = $parsed['dir'] ?? $this->defaultDir;

            if ('' === $alias || !isset($this->allowed[$alias])) {
                continue;
            }

            $resolved = $this->resolveMapping($this->allowed[$alias], $requestedDir);

            if ($resolved instanceof MultiSort) {
                foreach ($resolved->getSorts() as $sort) {
                    $sorts[] = $sort;
                }
            } else {
                $sorts[] = $resolved;
            }
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
     * Check if a column alias is allowed.
     */
    public function isAllowed(string $column): bool
    {
        return isset($this->allowed[$column]);
    }
}
