<?php
/*
 * This file is part of Hector ORM.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2026 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

declare(strict_types=1);

namespace Hector\Schema\Plan\Compiler\Dialect\Sqlite;

/**
 * A foreign key entry of the SQLite rebuild diff model.
 */
final class ForeignKeyDef
{
    /**
     * @param string   $name
     * @param string[] $columns
     * @param string   $referencedTable
     * @param string[] $referencedColumns
     * @param string   $onUpdate
     * @param string   $onDelete
     */
    public function __construct(
        public string $name,
        public array $columns,
        public string $referencedTable,
        public array $referencedColumns,
        public string $onUpdate,
        public string $onDelete,
    ) {
    }
}
