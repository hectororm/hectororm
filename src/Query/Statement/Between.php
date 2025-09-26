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

class Between implements StatementInterface
{
    protected const EXPRESSION = 'BETWEEN';

    protected StatementInterface|string $column;
    protected mixed $value1;
    protected mixed $value2;

    /**
     * Between constructor.
     *
     * @param StatementInterface|string $column
     * @param mixed $value1
     * @param mixed $value2
     */
    public function __construct(StatementInterface|string $column, mixed $value1, mixed $value2)
    {
        $this->column = $column;
        $this->value1 = $value1;
        $this->value2 = $value2;
    }

    /**
     * @inheritDoc
     */
    public function getStatement(BindParamList $bindParams, bool $encapsulate = false): ?string
    {
        if ($this->column instanceof StatementInterface) {
            return sprintf(
                '%s %s :%s AND :%s',
                $this->column->getStatement($bindParams, true),
                static::EXPRESSION,
                $bindParams->add($this->value1)->getName(),
                $bindParams->add($this->value2)->getName(),
            );
        }

        return sprintf(
            '%s %s :%s AND :%s',
            $this->column,
            static::EXPRESSION,
            $bindParams->add($this->value1)->getName(),
            $bindParams->add($this->value2)->getName(),
        );
    }
}
