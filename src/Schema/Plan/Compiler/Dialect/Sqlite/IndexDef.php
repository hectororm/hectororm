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

use Hector\Schema\Plan\Operation\AddIndex;

/**
 * An index entry of the SQLite rebuild diff model.
 */
final class IndexDef
{
    /**
     * @param string   $name
     * @param string[] $columns
     * @param string   $type
     */
    public function __construct(
        public string $name,
        public array $columns,
        public string $type,
    ) {
    }

    /**
     * Convert to an AddIndex operation for the given table.
     *
     * @param string $tableName
     *
     * @return AddIndex
     */
    public function toAddIndex(string $tableName): AddIndex
    {
        return new AddIndex(
            table: $tableName,
            name: $this->name,
            columns: $this->columns,
            type: $this->type,
        );
    }
}
