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
use Hector\Query\StatementInterface;

class Group extends AbstractComponent implements Countable
{
    private array $group = [];
    private bool $withRollup = false;

    /**
     * @inheritDoc
     */
    public function count(): int
    {
        return count($this->group);
    }

    /**
     * Group by.
     *
     * @param StatementInterface|string $column
     */
    public function groupBy(StatementInterface|string $column): void
    {
        $this->group[] = $column;
    }

    /**
     * With rollup?
     *
     * @param bool $withRollup
     */
    public function withRollup(bool $withRollup = true): void
    {
        $this->withRollup = $withRollup;
    }

    /**
     * @inheritDoc
     */
    public function getStatement(BindParamList $bindParams, bool $encapsulate = false): ?string
    {
        if (empty($this->group)) {
            return null;
        }

        return $this->encapsulate(
            'GROUP BY ' .
            implode(
                ', ',
                array_map(
                    function ($group) use (&$bindParams) {
                        return $this->getSubStatement($group, $bindParams);
                    },
                    $this->group
                )
            ) .
            ($this->withRollup ? ' WITH ROLLUP' : ''),
            $encapsulate
        );
    }
}
