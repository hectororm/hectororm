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
 * Trait Having.
 */
trait Having
{
    /** @internal */
    public Component\Conditions $having;

    /**
     * Reset having.
     *
     * @return static
     */
    public function resetHaving(): static
    {
        $this->having = new Component\Conditions($this);

        return $this;
    }

    /**
     * Having.
     *
     * @param mixed ...$condition
     *
     * @return static
     */
    public function having(mixed ...$condition): static
    {
        return $this->andHaving(...$condition);
    }

    /**
     * And having.
     *
     * @param mixed ...$condition
     *
     * @return static
     */
    public function andHaving(mixed ...$condition): static
    {
        $nbArgs = count($condition);

        if ($nbArgs === 0 || $nbArgs > 3) {
            throw new InvalidArgumentException();
        }

        match ($nbArgs) {
            1 => $this->having->add($condition[0]),
            2 => $this->having->equal($condition[0], $condition[1]),
            3 => $this->having->add($condition[0], $condition[1], $condition[2]),
        };

        return $this;
    }

    /**
     * Or having.
     *
     * @param mixed ...$condition
     *
     * @return static
     */
    public function orHaving(mixed ...$condition): static
    {
        $nbArgs = count($condition);

        if ($nbArgs === 0 || $nbArgs > 3) {
            throw new InvalidArgumentException();
        }

        match ($nbArgs) {
            1 => $this->having->add($condition[0], null, null, Component\Conditions::LINK_OR),
            2 => $this->having->equal($condition[0], $condition[1], Component\Conditions::LINK_OR),
            3 => $this->having->add($condition[0], $condition[1], $condition[2], Component\Conditions::LINK_OR),
        };

        return $this;
    }

    /**
     * Having equals.
     *
     * @param array $having
     *
     * @return static
     */
    public function havingEquals(array $having): static
    {
        $this->having->equals($having);

        return $this;
    }

    /**
     * Having in.
     *
     * @param Closure|StatementInterface|string $column
     * @param Closure|StatementInterface|iterable $values
     *
     * @return static
     */
    public function havingIn(
        Closure|StatementInterface|string $column,
        Closure|StatementInterface|iterable $values
    ): static {
        return $this->andHaving($column, 'IN', $values);
    }

    /**
     * Having not in.
     *
     * @param Closure|StatementInterface|string $column
     * @param Closure|StatementInterface|iterable $values
     *
     * @return static
     */
    public function havingNotIn(
        Closure|StatementInterface|string $column,
        Closure|StatementInterface|iterable $values
    ): static {
        return $this->andHaving($column, 'NOT IN', $values);
    }

    /**
     * Having null.
     *
     * @param Closure|StatementInterface|string $column
     *
     * @return static
     */
    public function havingNull(Closure|StatementInterface|string $column): static
    {
        $this->having->add($column, 'IS NULL');

        return $this;
    }

    /**
     * Having not null.
     *
     * @param Closure|StatementInterface|string $column
     *
     * @return static
     */
    public function havingNotNull(Closure|StatementInterface|string $column): static
    {
        $this->having->add($column, 'IS NOT NULL');

        return $this;
    }

    /**
     * Having between.
     *
     * @param Closure|StatementInterface|string $column
     * @param mixed $value1
     * @param mixed $value2
     *
     * @return static
     */
    public function havingBetween(Closure|StatementInterface|string $column, mixed $value1, mixed $value2): static
    {
        return $this->andHaving(new Between($column, $value1, $value2));
    }

    /**
     * Having between.
     *
     * @param Closure|StatementInterface|string $column
     * @param mixed $value1
     * @param mixed $value2
     *
     * @return static
     */
    public function havingNotBetween(Closure|StatementInterface|string $column, mixed $value1, mixed $value2): static
    {
        return $this->andHaving(new NotBetween($column, $value1, $value2));
    }

    /**
     * Having greater than.
     *
     * @param Closure|StatementInterface|string $column
     * @param mixed $value
     *
     * @return static
     */
    public function havingGreaterThan(Closure|StatementInterface|string $column, mixed $value): static
    {
        return $this->andHaving($column, '>', $value);
    }

    /**
     * Having greater than or equal.
     *
     * @param Closure|StatementInterface|string $column
     * @param mixed $value
     *
     * @return static
     */
    public function havingGreaterThanOrEqual(Closure|StatementInterface|string $column, mixed $value): static
    {
        return $this->andHaving($column, '>=', $value);
    }

    /**
     * Having less than.
     *
     * @param Closure|StatementInterface|string $column
     * @param mixed $value
     *
     * @return static
     */
    public function havingLessThan(Closure|StatementInterface|string $column, mixed $value): static
    {
        return $this->andHaving($column, '<', $value);
    }

    /**
     * Having less than or equal.
     *
     * @param Closure|StatementInterface|string $column
     * @param mixed $value
     *
     * @return static
     */
    public function havingLessThanOrEqual(Closure|StatementInterface|string $column, mixed $value): static
    {
        return $this->andHaving($column, '<=', $value);
    }

    /**
     * Having exists.
     *
     * @param Closure|StatementInterface|string $value
     *
     * @return static
     */
    public function havingExists(Closure|StatementInterface|string $value): static
    {
        return $this->andHaving(new SqlFunction('EXISTS', $value));
    }

    /**
     * Having not exists.
     *
     * @param Closure|StatementInterface|string $value
     *
     * @return static
     */
    public function havingNotExists(Closure|StatementInterface|string $value): static
    {
        return $this->andHaving(new SqlFunction('NOT EXISTS', $value));
    }

    /**
     * Having contains.
     *
     * @param Closure|StatementInterface|string $column
     * @param string $value
     *
     * @return static
     */
    public function havingContains(Closure|StatementInterface|string $column, string $value): static
    {
        return $this->andHaving($column, 'LIKE', sprintf('%%%s%%', $value));
    }

    /**
     * Having starts with.
     *
     * @param Closure|StatementInterface|string $column
     * @param string $value
     *
     * @return static
     */
    public function havingStartsWith(Closure|StatementInterface|string $column, string $value): static
    {
        return $this->andHaving($column, 'LIKE', sprintf('%s%%', $value));
    }

    /**
     * Having ends with.
     *
     * @param Closure|StatementInterface|string $column
     * @param string $value
     *
     * @return static
     */
    public function havingEndsWith(Closure|StatementInterface|string $column, string $value): static
    {
        return $this->andHaving($column, 'LIKE', sprintf('%%%s', $value));
    }
}