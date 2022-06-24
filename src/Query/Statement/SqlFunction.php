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

use Closure;
use Hector\Connection\Bind\BindParamList;
use Hector\Query\StatementInterface;

/**
 * Class SqlFunction.
 */
class SqlFunction implements StatementInterface
{
    protected string $function;
    protected Closure|StatementInterface|string $expression;

    /**
     * SqlFunction constructor.
     *
     * @param string $function
     * @param Closure|StatementInterface|string $expression
     */
    public function __construct(string $function, Closure|StatementInterface|string $expression)
    {
        $this->function = $function;
        $this->expression = $expression;
    }

    /**
     * @inheritDoc
     */
    public function getStatement(BindParamList $bindParams, bool $encapsulate = false): ?string
    {
        if ($this->expression instanceof StatementInterface) {
            return
                sprintf(
                    '%s( %s )',
                    $this->function,
                    $this->expression->getStatement($bindParams, false)
                );
        }

        return sprintf('%s( %s )', $this->function, $this->expression);
    }
}