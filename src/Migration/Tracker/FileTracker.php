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
        // Write to a temporary file in the same directory, then rename() over the target.
        // rename() is atomic on the same filesystem, so a crash mid-write cannot leave the
        // tracking file (the source of truth) truncated or partially written.
        $directory = dirname($this->filePath);
        $temporary = @tempnam($directory, '.hector-migration-');

        if (false === $temporary) {
            throw new MigrationException(
                sprintf('Cannot create a temporary file in: %s', $directory)
            );
        }

        $bytes = file_put_contents($temporary, $json, true === $this->lock ? LOCK_EX : 0);

        // Detect both a hard failure (false) and a partial write (fewer bytes than expected).
        if (false === $bytes || $bytes !== strlen($json)) {
            @unlink($temporary);

            throw new MigrationException(
                sprintf('Cannot write migration tracking file: %s', $this->filePath)
            );
        }

        if (false === @rename($temporary, $this->filePath)) {
            @unlink($temporary);

            throw new MigrationException(
                sprintf('Cannot write migration tracking file: %s', $this->filePath)
            );
        }
    }
}
