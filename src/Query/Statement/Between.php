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

use Hector\Query\Component\IndentHelperTrait;
use Hector\Query\StatementInterface;

/**
 * Class Between.
 */
class Between implements StatementInterface
{
    protected const EXPRESSION = 'BETWEEN';

    use IndentHelperTrait;

    protected StatementInterface|string $column;
    protected $value1;
    protected $value2;

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
    public function getStatement(array &$binding, bool $encapsulate = false): ?string
    {
        if ($this->column instanceof StatementInterface) {
            $statement = sprintf('%s %s ? AND ?', $this->column->getStatement($binding, true), static::EXPRESSION);

            $binding[] = $this->value1;
            $binding[] = $this->value2;

            return $statement;
        }

        $binding[] = $this->value1;
        $binding[] = $this->value2;

        return sprintf('%s %s ? AND ?', $this->column, static::EXPRESSION);
    }
}