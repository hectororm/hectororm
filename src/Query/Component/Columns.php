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

declare(strict_types=1);

namespace Hector\Query\Component;

use Countable;
use Hector\Connection\Bind\BindParamList;
use Hector\Connection\Driver\DriverCapabilities;
use Hector\Query\Helper;
use Hector\Query\StatementInterface;

class Columns extends AbstractComponent implements Countable
{
    private array $columns = [];

    /**
     * @inheritDoc
     */
    public function count(): int
    {
        return count($this->columns);
    }

    /**
     * Column.
     *
     * @param StatementInterface|string $column
     * @param string|null $alias
     */
    public function column(StatementInterface|string $column, ?string $alias = null): void
    {
        $this->columns[] = [
            'column' => $column,
            'alias' => Helper::trim($alias),
        ];
    }

    /**
     * Columns.
     *
     * @param StatementInterface|string ...$column
     */
    public function columns(StatementInterface|string ...$column): void
    {
        $column =
            array_map(
                fn($value): array => [
                    'column' => $value,
                    'alias' => null,
                ],
                $column
            );

        $this->columns = array_merge($this->columns, $column);
    }

    /**
     * @inheritDoc
     */
    public function getStatement(
        BindParamList $bindParams,
        ?DriverCapabilities $driverCapabilities = null,
    ): ?string {
        $quote = $driverCapabilities?->getIdentifierQuote() ?? '`';

        $str = implode(
            ', ',
            array_map(
                function ($column) use ($bindParams, $driverCapabilities, $quote) {
                    if ($column['alias']) {
                        return sprintf(
                            '%s AS %s',
                            rtrim($this->getSubStatement($column['column'], $bindParams, $driverCapabilities)),
                            Helper::quote($column['alias'], $quote),
                        );
                    }

                    return $this->getSubStatement($column['column'], $bindParams, $driverCapabilities);
                },
                $this->columns
            )
        );

        return $str ?: null;
    }
}
