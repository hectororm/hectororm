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

namespace Hector\Query;

use Closure;
use Hector\Connection\Bind\BindParamList;
use Hector\Connection\Driver\DriverInfo;
use Hector\Query\Clause\BindParams;
use Hector\Query\Clause\From;
use Hector\Query\Statement\AssignmentValueInterface;
use Hector\Query\Statement\Encapsulated;
use InvalidArgumentException;
use RuntimeException;

/**
 * Native upsert: insert rows, and update the existing ones on a uniqueness conflict.
 *
 * MySQL/MariaDB detect the conflict on any primary/unique index of the table and do not
 * honor the conflict columns; PostgreSQL and SQLite require a uniqueness constraint
 * matching them.
 */
class Upsert implements CompoundStatementInterface
{
    use BindParams;
    use From;

    /** Alias of the inserted row, on engines referencing it through a row alias. */
    public const ROW_ALIAS = 'hector_upsert';

    /** @var string[] Unquoted column names, in the order of the first row */
    protected array $columns = [];
    /** @var array<int, array<int, mixed>> Values of each row, in the order of the columns */
    protected array $rows = [];
    /** @var string[] Unquoted conflict column names */
    protected array $uniqueBy = [];
    /** @var string[]|null Unquoted names of the columns to update, null for all but the conflict ones */
    protected ?array $update = null;

    public function __construct(?BindParamList $binds = null)
    {
        $this->binds = $binds ?? new BindParamList();
        $this->reset();
    }

    /**
     * Reset.
     *
     * @return static
     */
    public function reset(): static
    {
        $this
            ->resetBindParams()
            ->resetFrom();
        $this->columns = [];
        $this->rows = [];
        $this->uniqueBy = [];
        $this->update = null;

        return $this;
    }

    /**
     * Rows to insert.
     *
     * Every row is an array of values indexed by column name, and all rows must have
     * the same columns (in any order). A single row is accepted as well.
     *
     * @param array $rows
     *
     * @return static
     * @throws InvalidArgumentException If a row is empty, not indexed by column name,
     *                                  or does not have the columns of the first row
     */
    public function rows(array $rows): static
    {
        $this->columns = [];
        $this->rows = [];

        if ([] === $rows) {
            return $this;
        }

        if (false === is_array(reset($rows))) {
            $rows = [$rows];
        }

        foreach ($rows as $index => $row) {
            $row = $this->normalizeRow($row, $index);

            if ([] === $this->columns) {
                $this->columns = array_map('strval', array_keys($row));
            }

            if (count($row) !== count($this->columns) || [] !== array_diff($this->columns, array_keys($row))) {
                throw new InvalidArgumentException(
                    sprintf('Upsert row "%s" does not have the same columns as the first row', $index)
                );
            }

            $this->rows[] = array_map(fn(string $column): mixed => $row[$column], $this->columns);
        }

        return $this;
    }

    /**
     * Columns identifying a conflict.
     *
     * @param string[] $columns
     *
     * @return static
     * @throws InvalidArgumentException If the list is empty or is not a list of column names
     */
    public function uniqueBy(array $columns): static
    {
        if ([] === $columns) {
            throw new InvalidArgumentException('Upsert requires at least one conflict column');
        }

        $this->uniqueBy = $this->normalizeColumns($columns, 'conflict');

        return $this;
    }

    /**
     * Columns to update on conflict.
     *
     * Null updates every inserted column but the conflict ones. With no column to
     * update, the existing rows are left untouched.
     *
     * @param string[]|null $columns
     *
     * @return static
     * @throws InvalidArgumentException If it is not a list of column names
     */
    public function update(?array $columns): static
    {
        $this->update = null === $columns ? null : $this->normalizeColumns($columns, 'update');

        return $this;
    }

    /**
     * @inheritDoc
     * @throws InvalidArgumentException If a conflict or update column is not part of the rows
     * @throws RuntimeException If the driver does not support a native upsert
     */
    public function getStatement(
        BindParamList $bindParams,
        ?DriverInfo $driverInfo = null,
    ): ?string {
        $capabilities = $driverInfo?->getCapabilities();

        if (null !== $driverInfo && false === $capabilities->hasUpsert()) {
            throw new RuntimeException(
                sprintf(
                    'Native upsert is not supported by driver "%s" (version "%s")',
                    $driverInfo->getDriver(),
                    $driverInfo->getVersion(),
                )
            );
        }

        $this->mergeBindParamsTo($bindParams);

        $fromStr = $this->from->getStatement($bindParams, $driverInfo);

        if (null === $fromStr || [] === $this->rows) {
            return null;
        }

        $update = $this->getColumnsToUpdate();
        $quote = $driverInfo?->getIdentifierQuote() ?? '`';
        $quoted = fn(string $column): string => (string)Helper::quote($column, $quote);

        $str = sprintf(
            'INSERT INTO %s ( %s ) VALUES %s',
            $fromStr,
            implode(', ', array_map($quoted, $this->columns)),
            implode(
                ', ',
                array_map(
                    fn(array $row): string => '( ' . implode(
                        ', ',
                        array_map(
                            fn(mixed $value): string => $this->getValueStatement($value, $bindParams, $driverInfo),
                            $row
                        )
                    ) . ' )',
                    $this->rows
                )
            ),
        );

        // PostgreSQL and SQLite target the conflict columns and expose the inserted row as "excluded".
        if (true === in_array($driverInfo?->getDriver(), ['pgsql', 'sqlite'], true)) {
            $str .= ' ON CONFLICT ( ' . implode(', ', array_map($quoted, $this->uniqueBy)) . ' )';

            if ([] === $update) {
                return $str . ' DO NOTHING';
            }

            return $str . ' DO UPDATE SET ' . implode(
                ', ',
                array_map(
                    fn(string $column): string => sprintf('%1$s = excluded.%1$s', $quoted($column)),
                    $update
                )
            );
        }

        // MySQL/MariaDB (also the fallback without driver) detect the conflict on any unique index.
        // They have no "do nothing": assign a conflict column to itself, which leaves the row untouched.
        if ([] === $update) {
            return $str . sprintf(' ON DUPLICATE KEY UPDATE %1$s = %1$s', $quoted($this->uniqueBy[0]));
        }

        if (true === $capabilities?->hasInsertRowAlias()) {
            return $str . ' AS ' . $quoted(self::ROW_ALIAS) . ' ON DUPLICATE KEY UPDATE ' . implode(
                ', ',
                array_map(
                    fn(string $column): string => sprintf(
                        '%1$s = %2$s.%1$s',
                        $quoted($column),
                        $quoted(self::ROW_ALIAS)
                    ),
                    $update
                )
            );
        }

        return $str . ' ON DUPLICATE KEY UPDATE ' . implode(
            ', ',
            array_map(
                fn(string $column): string => sprintf('%1$s = VALUES(%1$s)', $quoted($column)),
                $update
            )
        );
    }

    /**
     * Get columns to update on conflict.
     *
     * @return string[]
     * @throws InvalidArgumentException If a conflict or update column is not part of the rows
     */
    private function getColumnsToUpdate(): array
    {
        if ([] === $this->uniqueBy) {
            throw new InvalidArgumentException('Upsert requires at least one conflict column');
        }

        $this->assertColumnsInRows($this->uniqueBy, 'conflict');

        if (null === $this->update) {
            return array_values(array_diff($this->columns, $this->uniqueBy));
        }

        $this->assertColumnsInRows($this->update, 'update');

        return $this->update;
    }

    /**
     * Assert columns are part of the rows.
     *
     * @param string[] $columns
     * @param string $usage
     *
     * @return void
     * @throws InvalidArgumentException
     */
    private function assertColumnsInRows(array $columns, string $usage): void
    {
        $missing = array_diff($columns, $this->columns);

        if ([] === $missing) {
            return;
        }

        throw new InvalidArgumentException(
            sprintf('Upsert %s column "%s" is not part of the rows', $usage, reset($missing))
        );
    }

    /**
     * Normalize a row: unquoted column names, validated values.
     *
     * @param mixed $row
     * @param int|string $index
     *
     * @return array<string, mixed>
     * @throws InvalidArgumentException
     */
    private function normalizeRow(mixed $row, int|string $index): array
    {
        if (false === is_array($row) || [] === $row) {
            throw new InvalidArgumentException(
                sprintf('Upsert row "%s" must be a non-empty array of values indexed by column name', $index)
            );
        }

        $normalized = [];

        foreach ($row as $column => $value) {
            $name = is_string($column) ? Helper::unquote($column) : null;

            if (null === $name || '' === $name) {
                throw new InvalidArgumentException(
                    sprintf('Upsert row "%s" must be indexed by column name', $index)
                );
            }

            if (true === array_key_exists($name, $normalized)) {
                throw new InvalidArgumentException(
                    sprintf('Upsert row "%s" defines column "%s" twice', $index, $name)
                );
            }

            if (is_array($value) || $value instanceof Closure || $value instanceof AssignmentValueInterface) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Upsert row "%s" has an unsupported value of type %s for column "%s"',
                        $index,
                        get_debug_type($value),
                        $name,
                    )
                );
            }

            $normalized[$name] = $value;
        }

        return $normalized;
    }

    /**
     * Normalize a list of column names: unquoted, without duplicates.
     *
     * @param array $columns
     * @param string $usage
     *
     * @return string[]
     * @throws InvalidArgumentException
     */
    private function normalizeColumns(array $columns, string $usage): array
    {
        $normalized = [];

        foreach ($columns as $key => $column) {
            $name = is_int($key) && is_string($column) ? Helper::unquote($column) : null;

            if (null === $name || '' === $name) {
                throw new InvalidArgumentException(
                    sprintf('Upsert %s columns must be a list of column names', $usage)
                );
            }

            $normalized[$name] = $name;
        }

        return array_values($normalized);
    }

    /**
     * Get the statement of a value: statements are rendered, anything else is bound.
     *
     * @param mixed $value
     * @param BindParamList $bindParams
     * @param DriverInfo|null $driverInfo
     *
     * @return string
     */
    private function getValueStatement(mixed $value, BindParamList $bindParams, ?DriverInfo $driverInfo): string
    {
        if ($value instanceof CompoundStatementInterface) {
            return (string)(new Encapsulated($value))->getStatement($bindParams, $driverInfo);
        }

        if ($value instanceof StatementInterface) {
            return (string)$value->getStatement($bindParams, $driverInfo);
        }

        return ':' . $bindParams->add($value)->getName();
    }
}
