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

namespace Hector\Schema\Tests\Generator;

use Hector\Schema\Generator\Sqlite;

class FakeSqlite extends Sqlite
{
    public function getSchemaInfo(string $name): array
    {
        return parent::getSchemaInfo($name);
    }

    public function getTablesInfo(string $name): array
    {
        return parent::getTablesInfo($name);
    }

    public function getColumnsInfo(string $schema, string $table): array
    {
        return parent::getColumnsInfo($schema, $table);
    }

    public function getIndexesInfo(string $schema, string $table): array
    {
        return parent::getIndexesInfo($schema, $table);
    }

    public function getForeignKeysInfo(string $schema, string $table): array
    {
        return parent::getForeignKeysInfo($schema, $table);
    }
}