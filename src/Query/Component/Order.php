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

use Closure;
use Countable;
use Hector\Connection\Bind\BindParamList;
use Hector\Query\StatementInterface;

/**
 * Class Order.
 */
class Order extends AbstractComponent implements Countable
{
    public const ORDER_ASC = 'ASC';
    public const ORDER_DESC = 'DESC';

    private array $order = [];

    /**
     * @inheritDoc
     */
    public function count(): int
    {
        return count($this->order);
    }

    /**
     * Order by.
     *
     * @param Closure|StatementInterface|string $column
     * @param string|null $order
     */
    public function orderBy(Closure|StatementInterface|string $column, ?string $order = null): void
    {
        $this->order[] = [
            'column' => $column,
            'order' => $order,
        ];
    }

    /**
     * @inheritDoc
     */
    public function getStatement(BindParamList $bindParams, bool $encapsulate = false): ?string
    {
        if (empty($this->order)) {
            return null;
        }

        return $this->encapsulate(
            'ORDER BY ' .
            implode(
                ', ',
                array_map(
                    function ($column) use (&$bindParams) {
                        if ($column['order']) {
                            return sprintf(
                                '%s %s',
                                $this->getSubStatement($column['column'], $bindParams),
                                $column['order']
                            );
                        }

                        return $this->getSubStatement($column['column'], $bindParams);
                    },
                    $this->order
                )
            ),
            $encapsulate
        );
    }
}