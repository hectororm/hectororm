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

namespace Hector\Query\Statement;

use Hector\Connection\Bind\BindParamList;
use Hector\Query\StatementInterface;

class Raw implements StatementInterface
{
    /**
     * Raw constructor.
     *
     * @param string $expression
     * @param array $binds
     */
    public function __construct(private string $expression, private array $binds = [])
    {
    }

    /**
     * @inheritDoc
     */
    public function getStatement(BindParamList $bindParams, bool $encapsulate = false): ?string
    {
        array_map(
            fn($name, $value) => $bindParams->add($value, name: (string)$name),
            array_keys($this->binds),
            array_values($this->binds)
        );

        return $this->expression;
    }
}
