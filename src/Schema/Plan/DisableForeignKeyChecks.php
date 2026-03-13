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

namespace Hector\Schema\Plan;

use Hector\Schema\Plan\Operation\PreOperationInterface;

/**
 * Disable foreign key checks.
 *
 * When added to a plan, this operation is emitted in the Pre pass (before all
 * other operations), producing driver-specific SQL to disable FK constraint
 * checking (e.g., SET FOREIGN_KEY_CHECKS = 0 on MySQL, PRAGMA foreign_keys = OFF
 * on SQLite).
 */
final class DisableForeignKeyChecks implements OperationInterface, PreOperationInterface
{
    /**
     * @inheritDoc
     */
    public function getObjectName(): ?string
    {
        return null;
    }
}
