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

final class MigrateData implements OperationInterface
{
    /**
     * MigrateData constructor.
     *
     * @param string $table Source table name
     * @param string $targetTable Destination table name
     * @param array<string, string> $columnMapping Column mapping ['source_col' => 'target_col', ...], empty for SELECT *
     */
    public function __construct(
        private string $table,
        private string $targetTable,
        private array $columnMapping = [],
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
     * Get the target table name.
     *
     * @return string
     */
    public function getTargetTable(): string
    {
        return $this->targetTable;
    }

    /**
     * Get the column mapping.
     *
     * @return array<string, string>
     */
    public function getColumnMapping(): array
    {
        return $this->columnMapping;
    }
}
