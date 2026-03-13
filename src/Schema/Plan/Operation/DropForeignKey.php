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

use Hector\Schema\Plan\OperationInterface;

final class DropForeignKey implements OperationInterface, PreOperationInterface
{
    public function __construct(
        private string $table,
        private string $name,
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
     * Get foreign key name.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }
}
