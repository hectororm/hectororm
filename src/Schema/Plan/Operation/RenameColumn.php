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

namespace Hector\Schema\Plan\Operation;

class RenameColumn implements ColumnOperationInterface
{
    public function __construct(
        private string $table,
        private string $name,
        private string $newName,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getObjectName(): string
    {
        return $this->table;
    }

    /**
     * Get column name.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get new column name.
     *
     * @return string
     */
    public function getNewName(): string
    {
        return $this->newName;
    }
}
