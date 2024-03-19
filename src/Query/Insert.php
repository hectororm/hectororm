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

use Closure;
use Hector\Connection\Bind\BindParamList;

class Insert implements StatementInterface
{
    use Clause\BindParams;
    use Clause\Columns;
    use Clause\From;
    use Clause\Assignments;
    use Component\EncapsulateHelperTrait;

    protected ?Select $select;
    protected bool|Closure $ignore = false;

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
            ->resetColumns()
            ->resetFrom()
            ->resetAssignments()
            ->resetSelect();
        is_bool($this->ignore) && $this->ignore = false;

        return $this;
    }

    /**
     * Reset select.
     *
     * @return static
     */
    public function resetSelect(): static
    {
        $this->select = null;

        return $this;
    }

    /**
     * Select assignment.
     *
     * @param Select $select
     *
     * @return static
     */
    public function select(Select $select): static
    {
        $this->select = $select;

        return $this;
    }

    /**
     * Ignore duplicates.
     *
     * @param bool|Closure $ignore
     *
     * @return static
     */
    public function ignore(bool|Closure $ignore = true): static
    {
        $this->ignore = $ignore;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getStatement(BindParamList $bindParams, bool $encapsulate = false): ?string
    {
        $this->mergeBindParamsTo($bindParams);

        $fromStr = $this->from->getStatement($bindParams);
        $assignmentsStr = $this->assignments->getStatement($bindParams);

        if (null === $fromStr || null === $assignmentsStr) {
            return null;
        }

        $str = 'INSERT';

        if ((true === $this->ignore || ($this->ignore instanceof Closure && true === ($this->ignore)()))) {
            $str .= ' IGNORE';
        }

        $str .= ' INTO ' . ($this->from->getStatement($bindParams) ?? '') . ' ' .
            (false === $this->assignments->isStatement() ? 'SET ' : '') .
            $assignmentsStr;

        return $this->encapsulate($str, $encapsulate);
    }
}