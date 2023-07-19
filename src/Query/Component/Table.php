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

use Hector\Connection\Bind\BindParamList;
use Hector\Query\Helper;
use Hector\Query\StatementInterface;

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
            'alias' => Helper::trim($alias),
        ];
    }

    /**
     * Use alias.
     *
     * @param bool $alias
     */
    public function useAlias(bool $alias = true): void
    {
        $this->alias = $alias;
    }

    /**
     * @inheritDoc
     */
    public function getStatement(BindParamList $bindParams, bool $encapsulate = false): ?string
    {
        return $this->encapsulate(
            implode(
                ', ',
                array_map(
                    function ($from) use (&$bindParams) {
                        if ($from['alias'] && $this->alias) {
                            return sprintf(
                                '%s AS %s',
                                $this->getSubStatement($from['table'], $bindParams),
                                Helper::quote($from['alias'])
                            );
                        }

                        return $this->getSubStatement($from['table'], $bindParams);
                    },
                    $this->tables
                )
            ),
            $encapsulate
        );
    }
}