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
use Hector\Connection\Connection;
use Hector\Query\Statement\Exists;

/**
 * Class QueryBuilder.
 */
class QueryBuilder
{
    use Clause\Assignments;
    use Clause\Columns;
    use Clause\From;
    use Clause\Join;
    use Clause\Where;
    use Clause\Group;
    use Clause\Having;
    use Clause\Order;
    use Clause\Limit;

    protected Connection $connection;
    private bool $distinct = false;

    /**
     * QueryBuilder constructor.
     *
     * @param Connection $connection
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
        $this->reset();
    }

    /**
     * __clone() magic method.
     */
    public function __clone(): void
    {
        $this->assignments = clone $this->assignments;
        $this->columns = clone $this->columns;
        $this->from = clone $this->from;
        $this->join = clone $this->join;
        $this->where = clone $this->where;
        $this->group = clone $this->group;
        $this->having = clone $this->having;
        $this->order = clone $this->order;
        $this->limit = clone $this->limit;

        $this->assignments->builder = $this;
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
            ->resetAssignments()
            ->resetColumns()
            ->resetFrom()
            ->resetJoin()
            ->resetWhere()
            ->resetGroup()
            ->resetHaving()
            ->resetOrder()
            ->resetLimit();

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

        $select = new Select();
        $select->distinct($this->distinct);
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
        $countSelect =
            $this
                ->makeSelect()
                ->resetColumns()
                ->resetLimit()
                ->resetOrder();

        if (count($this->group) > 0) {
            $countSelect = (new Select())->from($countSelect->column('1'), 'countable');
        }

        return $countSelect->column('COUNT(*)', '`count`');
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
        $insert = new Insert();
        $insert->assignments = clone $this->assignments;
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
        $update = new Update();
        $update->assignments = clone $this->assignments;
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
        $delete = new Delete();
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

        $binding = [];
        $statement = $select->getStatement($binding);

        return $this->connection->fetchOne($statement, $binding);
    }

    /**
     * Fetch all.
     *
     * @return Generator
     */
    public function fetchAll(): Generator
    {
        $select = $this->makeSelect();

        $binding = [];
        $statement = $select->getStatement($binding);

        yield from $this->connection->fetchAll($statement, $binding);
    }

    /**
     * Fetch all.
     *
     * @param int $column
     *
     * @return Generator
     */
    public function fetchColumn(int $column = 0): Generator
    {
        $select = $this->makeSelect();

        $binding = [];
        $statement = $select->getStatement($binding);

        yield from $this->connection->fetchColumn($statement, $binding, $column);
    }

    /**
     * Count.
     *
     * @return int
     */
    public function count(): int
    {
        $select = $this->makeCount();

        $binding = [];
        $statement = $select->getStatement($binding);
        $result = $this->connection->fetchOne($statement, $binding);

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
        $binding = [];
        $statement = $this->makeExists()->getStatement($binding);

        $result = $this->connection->fetchOne($statement, $binding);

        if (null === $result) {
            return false;
        }

        return $result['exists'] == 1;
    }

    /**
     * Insert.
     *
     * @param array $values
     *
     * @return int
     */
    public function insert(array $values = []): int
    {
        $insert = $this->makeInsert();
        $insert->assigns($values);

        $binding = [];
        $statement = $insert->getStatement($binding);

        return $this->connection->execute($statement, $binding);
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

        $binding = [];
        $statement = $update->getStatement($binding);

        return $this->connection->execute($statement, $binding);
    }

    /**
     * Delete.
     *
     * @return int
     */
    public function delete(): int
    {
        $delete = $this->makeDelete();

        $binding = [];
        $statement = $delete->getStatement($binding);

        return $this->connection->execute($statement, $binding);
    }
}