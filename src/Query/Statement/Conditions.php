<?php
/*
 * This file is part of Hector ORM.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2023 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

declare(strict_types=1);

namespace Hector\Query\Statement;

use Hector\Query\Clause\Where;
use Hector\Query\Clause\Having;
use Hector\Connection\Bind\BindParamList;
use Hector\Query\StatementInterface;

class Conditions implements StatementInterface
{
    use Where;
    use Having;

    public function __construct(protected mixed $builder)
    {
        $this->reset();
    }

    /**
     * Reset.
     *
     * @return void
     */
    public function reset(): void
    {
        $this->resetWhere();
        $this->resetHaving();
    }

    /**
     * @inheritDoc
     */
    public function getStatement(BindParamList $bindParams, bool $encapsulate = false): ?string
    {
        return
            $this->where->getStatement($bindParams, $encapsulate) .
            $this->having->getStatement($bindParams, $encapsulate);
    }
}
