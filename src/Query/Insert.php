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

use Hector\Connection\Bind\BindParamList;

class Insert implements StatementInterface
{
    use Clause\BindParams;
    use Clause\From;
    use Clause\Assignments;
    use Component\EncapsulateHelperTrait;

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
            ->resetFrom()
            ->resetAssignments();

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

        $str = 'INSERT INTO ' . ($this->from->getStatement($bindParams) ?? '') . ' SET ' . $assignmentsStr;

        return $this->encapsulate($str, $encapsulate);
    }
}