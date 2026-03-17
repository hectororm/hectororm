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

namespace Hector\Migration\Tracker;

use Hector\Migration\Exception\MigrationException;

class FileTracker extends AbstractJsonTracker
{
    /**
     * FileTracker constructor.
     *
     * @param string $filePath Path to the JSON tracking file
     * @param bool $lock Use exclusive lock when writing (disable for NFS)
     */
    public function __construct(
        private string $filePath,
        private bool $lock = true,
    ) {
    }

    /**
     * @inheritDoc
     */
    protected function storageExists(): bool
    {
        return file_exists($this->filePath);
    }

    /**
     * @inheritDoc
     */
    protected function readStorage(): string
    {
        $content = file_get_contents($this->filePath);

        if (false === $content) {
            throw new MigrationException(
                sprintf('Cannot read migration tracking file: %s', $this->filePath)
            );
        }

        return $content;
    }

    /**
     * @inheritDoc
     */
    protected function writeStorage(string $json): void
    {
        $result = file_put_contents($this->filePath, $json, true === $this->lock ? LOCK_EX : 0);

        if (false === $result) {
            throw new MigrationException(
                sprintf('Cannot write migration tracking file: %s', $this->filePath)
            );
        }
    }
}
