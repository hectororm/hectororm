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

use Hector\Schema\Exception\PlanException;
use Hector\Schema\Plan\OperationInterface;

final class DropCheck implements OperationInterface
{
    public function __construct(private string $table, private string $name)
    {
        if ('' === trim($name)) {
            throw new PlanException('Dropping a CHECK constraint requires a non-empty name');
        }
    }

    /**
     * @inheritDoc
     */
    public function getObjectName(): string
    {
        return $this->table;
    }

    /**
     * Get constraint name.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }
}
