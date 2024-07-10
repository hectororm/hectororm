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

namespace Hector\Query;

use Generator;
use Hector\Connection\Bind\BindParamList;
use Hector\Connection\Connection;
use Hector\Query\Component\InsertAssignments;
use Hector\Query\Component\UpdateAssignments;
use Hector\Query\Statement\Exists;

class QueryBuilder implements StatementInterface
{
    use Clause\BindParams;
    use Clause\Columns;
    use Clause\From;
    use Clause\Join;
    use Clause\Where;
    use Clause\Group;
    use Clause\Having;
    use Clause\Order;
    use Clause\Limit;

    protected bool $distinct = false;
    protected bool $ignore = false;

    /**
     * QueryBuilder constructor.
     *
     * @param Connection $connection
     */
    public function __construct(protected Connection $connection)
    {
        $this->reset();
    }

    /**
     * __clone() magic method.
     */
    public function __clone(): void
    {
        $this->binds = clone $this->binds;
        $this->columns = clone $this->columns;
        $this->from = clone $this->from;
        $this->join = clone $this->join;
        $this->where = clone $this->where;
        $this->group = clone $this->group;
        $this->having = clone $this->having;
        $this->order = clone $this->order;
        $this->limit = clone $this->limit;

        $this->columns->builder = $this;
        $this->from->builder = $this;
        $this->join->builder = $this;
        $this->where->builder = $this;
        $this->group->builder = $this;
        $this->having->builder = $this;
        $this->order->builder = $this;
        $this->limit->builder = $this;
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
            ->resetColumns()
            ->resetFrom()
            ->resetJoin()
            ->resetWhere()
            ->resetGroup()
            ->resetHaving()
            ->resetOrder()
            ->resetLimit();
        $this->distinct = false;
        $this->ignore = false;

        return $this;
    }

    /**
     * Distinct result.
     *
     * @param bool $distinct
     *
     * @return static
     */
    public function distinct(bool $distinct = true): static
    {
        $this->distinct = $distinct;

        return $this;
    }

    /**
     * Ignore duplicates.
     *
     * @param bool $ignore
     *
     * @return static
     */
    public function ignore(bool $ignore = true): static
    {
        $this->ignore = $ignore;

        return $this;
    }

    /**
     * Select table.
     *
     * @param StatementInterface|string $table
     * @param string|null $alias
     *
     * @return static
     */
    public function select(StatementInterface|string $table, ?string $alias = null): static
    {
        $this->from($table, $alias);

        return $this;
    }

    ///////////////////////
    /// STATEMENT MAKER ///
    ///////////////////////

    /**
     * Make select.
     *
     * @return Select
     */
    protected function makeSelect(): Select
    {
        $queryBuilder = clone $this;

        $select = new Select($this->getBindParams());
        $select->distinct(fn() => $queryBuilder->distinct);
        $select->columns = $queryBuilder->columns;
        $select->from = $queryBuilder->from;
        $select->join = $queryBuilder->join;
        $select->where = $queryBuilder->where;
        $select->group = $queryBuilder->group;
        $select->having = $queryBuilder->having;
        $select->order = $queryBuilder->order;
        $select->limit = $queryBuilder->limit;

        return $select;
    }

    /**
     * Male count.
     *
     * @return Select
     */
    protected function makeCount(): Select
    {
        $select = $this->makeSelect();
        $count =
            (new Select())
                ->column('COUNT(*)', '`count`')
                ->from(
                    $select
                        ->resetLimit()
                        ->resetOrder(),
                    'countable'
                );

        if (count($this->having) == 0) {
            if (false === $this->distinct) {
                $select->resetColumns()->column('1');
            }
        }

        return $count;
    }

    /**
     * Make exists.
     *
     * @return Select
     */
    protected function makeExists(): Select
    {
        $select = new Select();
        $select->column(
            new Exists(
                $this
                    ->makeSelect()
                    ->resetColumns()
                    ->column('1')
            ),
            '`exists`'
        );

        return $select;
    }

    /**
     * Make insert.
     *
     * @return Insert
     */
    protected function makeInsert(): Insert
    {
        $queryBuilder = clone $this;

        $insert = new Insert($this->getBindParams());
        $insert->ignore(fn() => $queryBuilder->ignore);
        $insert->from = clone $this->from;
        $insert->from->useAlias(false);

        return $insert;
    }

    /**
     * Make update.
     *
     * @return Update
     */
    protected function makeUpdate(): Update
    {
        $update = new Update($this->getBindParams());
        $update->from = clone $this->from;
        $update->where = clone $this->where;
        $update->order = clone $this->order;
        $update->limit = clone $this->limit;

        return $update;
    }

    /**
     * Make delete.
     *
     * @return Delete
     */
    protected function makeDelete(): Delete
    {
        $delete = new Delete($this->getBindParams());
        $delete->from = clone $this->from;
        $delete->from->useAlias(false);
        $delete->where = clone $this->where;
        $delete->order = clone $this->order;
        $delete->limit = clone $this->limit;

        return $delete;
    }

    //////////////////////////
    /// EXECUTE STATEMENTS ///
    //////////////////////////

    /**
     * Fetch one.
     *
     * @return array|null
     */
    public function fetchOne(): ?array
    {
        $select = $this->makeSelect();

        $binds = new BindParamList();
        $statement = $select->getStatement($binds);

        return $this->connection->fetchOne($statement, $binds->getArrayCopy());
    }

    /**
     * Fetch all.
     *
     * @return Generator<array>
     */
    public function fetchAll(): Generator
    {
        $select = $this->makeSelect();

        $binds = new BindParamList();
        $statement = $select->getStatement($binds);

        yield from $this->connection->fetchAll($statement, $binds->getArrayCopy());
    }

    /**
     * Fetch all.
     *
     * @param int $column
     *
     * @return Generator<mixed>
     */
    public function fetchColumn(int $column = 0): Generator
    {
        $select = $this->makeSelect();

        $binds = new BindParamList();
        $statement = $select->getStatement($binds);

        yield from $this->connection->fetchColumn($statement, $binds->getArrayCopy(), $column);
    }

    /**
     * Count.
     *
     * @return int
     */
    public function count(): int
    {
        $select = $this->makeCount();

        $binds = new BindParamList();
        $statement = $select->getStatement($binds);
        $result = $this->connection->fetchOne($statement, $binds->getArrayCopy());

        if (null === $result) {
            return 0;
        }

        return (int)$result['count'];
    }

    /**
     * Exists?
     *
     * @return bool
     */
    public function exists(): bool
    {
        $binds = new BindParamList();
        $statement = $this->makeExists()->getStatement($binds);

        $result = $this->connection->fetchOne($statement, $binds->getArrayCopy());

        if (null === $result) {
            return false;
        }

        return $result['exists'] == 1;
    }

    /**
     * Execute statement on connection.
     *
     * @param string $statement
     * @param BindParamList|array $input_parameters
     *
     * @return int
     */
    protected function execute(string $statement, BindParamList|array $input_parameters = []): int
    {
        return $this->connection->execute($statement, $input_parameters);
    }

    /**
     * Insert.
     *
     * @param array|StatementInterface $values
     *
     * @return int
     */
    public function insert(array|StatementInterface $values = []): int
    {
        $insert = $this->makeInsert();
        $insert->assigns($values);

        $binds = new BindParamList();
        $statement = $insert->getStatement($binds);

        return $this->execute($statement, $binds->getArrayCopy());
    }

    /**
     * Update.
     *
     * @param array $values
     *
     * @return int
     */
    public function update(array $values = []): int
    {
        $update = $this->makeUpdate();
        $update->assigns($values);

        $binds = new BindParamList();
        $statement = $update->getStatement($binds);

        return $this->execute($statement, $binds->getArrayCopy());
    }

    /**
     * Delete.
     *
     * @return int
     */
    public function delete(): int
    {
        $delete = $this->makeDelete();

        $binds = new BindParamList();
        $statement = $delete->getStatement($binds);

        return $this->execute($statement, $binds->getArrayCopy());
    }

    /**
     * @inheritDoc
     */
    public function getStatement(BindParamList $bindParams, bool $encapsulate = false): ?string
    {
        return $this->makeSelect()->getStatement($bindParams, $encapsulate);
    }
}