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

namespace Hector\Query\Clause;

use Closure;
use Hector\Query\Component;
use Hector\Query\Statement\Between;
use Hector\Query\Statement\NotBetween;
use Hector\Query\Statement\SqlFunction;
use Hector\Query\StatementInterface;
use InvalidArgumentException;

/**
 * Trait Where.
 *
 * @package Hector\Query\Clause
 */
trait Where
{
    /** @internal */
    public Component\Conditions $where;

    /**
     * Reset where.
     *
     * @return static
     */
    public function resetWhere(): static
    {
        $this->where = new Component\Conditions($this);

        return $this;
    }

    /**
     * Where.
     *
     * @param mixed ...$condition
     *
     * @return static
     */
    public function where(mixed ...$condition): static
    {
        return $this->andWhere(...$condition);
    }

    /**
     * And where.
     *
     * @param mixed ...$condition
     *
     * @return static
     */
    public function andWhere(mixed ...$condition): static
    {
        $nbArgs = count($condition);

        if ($nbArgs === 0 || $nbArgs > 3) {
            throw new InvalidArgumentException();
        }

        match ($nbArgs) {
            1 => $this->where->add($condition[0]),
            2 => $this->where->equal($condition[0], $condition[1]),
            3 => $this->where->add($condition[0], $condition[1], $condition[2]),
        };

        return $this;
    }

    /**
     * Or where.
     *
     * @param mixed ...$condition
     *
     * @return static
     */
    public function orWhere(mixed ...$condition): static
    {
        $nbArgs = count($condition);

        if ($nbArgs === 0 || $nbArgs > 3) {
            throw new InvalidArgumentException();
        }

        match ($nbArgs) {
            1 => $this->where->add($condition[0], null, null, Component\Conditions::LINK_OR),
            2 => $this->where->equal($condition[0], $condition[1], Component\Conditions::LINK_OR),
            3 => $this->where->add($condition[0], $condition[1], $condition[2], Component\Conditions::LINK_OR),
        };

        return $this;
    }

    /**
     * Where equals.
     *
     * @param array $where
     *
     * @return static
     */
    public function whereEquals(array $where): static
    {
        $this->where->equals($where);

        return $this;
    }

    /**
     * Where in.
     *
     * @param Closure|StatementInterface|string $column
     * @param Closure|StatementInterface|iterable $values
     *
     * @return static
     */
    public function whereIn(
        Closure|StatementInterface|string $column,
        Closure|StatementInterface|iterable $values
    ): static {
        return $this->andWhere($column, 'IN', $values);
    }

    /**
     * Where not in.
     *
     * @param Closure|StatementInterface|string $column
     * @param Closure|StatementInterface|iterable $values
     *
     * @return static
     */
    public function whereNotIn(
        Closure|StatementInterface|string $column,
        Closure|StatementInterface|iterable $values
    ): static {
        return $this->andWhere($column, 'NOT IN', $values);
    }

    /**
     * Where between.
     *
     * @param Closure|StatementInterface|string $column
     * @param mixed $value1
     * @param mixed $value2
     *
     * @return static
     */
    public function whereBetween(Closure|StatementInterface|string $column, mixed $value1, mixed $value2): static
    {
        return $this->andWhere(new Between($column, $value1, $value2));
    }

    /**
     * Where between.
     *
     * @param Closure|StatementInterface|string $column
     * @param mixed $value1
     * @param mixed $value2
     *
     * @return static
     */
    public function whereNotBetween(Closure|StatementInterface|string $column, mixed $value1, mixed $value2): static
    {
        return $this->andWhere(new NotBetween($column, $value1, $value2));
    }

    /**
     * Where greater than.
     *
     * @param Closure|StatementInterface|string $column
     * @param mixed $value
     *
     * @return static
     */
    public function whereGreaterThan(Closure|StatementInterface|string $column, mixed $value): static
    {
        return $this->andWhere($column, '>', $value);
    }

    /**
     * Where greater than or equal.
     *
     * @param Closure|StatementInterface|string $column
     * @param mixed $value
     *
     * @return static
     */
    public function whereGreaterThanOrEqual(Closure|StatementInterface|string $column, mixed $value): static
    {
        return $this->andWhere($column, '>=', $value);
    }

    /**
     * Where less than.
     *
     * @param Closure|StatementInterface|string $column
     * @param mixed $value
     *
     * @return static
     */
    public function whereLessThan(Closure|StatementInterface|string $column, mixed $value): static
    {
        return $this->andWhere($column, '<', $value);
    }

    /**
     * Where less than or equal.
     *
     * @param Closure|StatementInterface|string $column
     * @param mixed $value
     *
     * @return static
     */
    public function whereLessThanOrEqual(Closure|StatementInterface|string $column, mixed $value): static
    {
        return $this->andWhere($column, '<=', $value);
    }

    /**
     * Where exists.
     *
     * @param Closure|StatementInterface|string $value
     *
     * @return static
     */
    public function whereExists(Closure|StatementInterface|string $value): static
    {
        return $this->andWhere(new SqlFunction('EXISTS', $value));
    }

    /**
     * Where not exists.
     *
     * @param Closure|StatementInterface|string $value
     *
     * @return static
     */
    public function whereNotExists(Closure|StatementInterface|string $value): static
    {
        return $this->andWhere(new SqlFunction('NOT EXISTS', $value));
    }
}