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

use Hector\Query\StatementInterface;

/**
 * Class Table.
 *
 * @package Hector\Query\Component
 */
class Table extends AbstractComponent
{
    private array $tables = [];
    private bool $alias = true;

    /**
     * Table.
     *
     * @param StatementInterface|string $table
     * @param string|null $alias
     */
    public function table(StatementInterface|string $table, ?string $alias = null): void
    {
        $this->tables[] = [
            'table' => $table,
            'alias' => $alias,
        ];
    }

    /**
     * Use alias.
     *
     * @param bool $alias
     */
    public function useAlias(bool $alias = true)
    {
        $this->alias = $alias;
    }

    /**
     * @inheritDoc
     */
    public function getStatement(array &$binding, bool $encapsulate = false): ?string
    {
        if (count($this->tables) === 0) {
            return null;
        }

        return
            $this->indent(
                implode(
                    ',' . PHP_EOL,
                    array_map(
                        function ($from) use (&$binding) {
                            if ($from['alias'] && $this->alias) {
                                return sprintf(
                                    '%s AS %s',
                                    $this->getSubStatement($from['table'], $binding),
                                    $from['alias']
                                );
                            }

                            return $this->getSubStatement($from['table'], $binding);
                        },
                        $this->tables
                    )
                )
            ) . PHP_EOL;
    }
}