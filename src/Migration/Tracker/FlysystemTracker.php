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
use League\Flysystem\FilesystemOperator;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToWriteFile;

class FlysystemTracker extends AbstractJsonTracker
{
    /**
     * FlysystemTracker constructor.
     *
     * @param FilesystemOperator $filesystem
     * @param string $filePath Path within the filesystem
     */
    public function __construct(
        private FilesystemOperator $filesystem,
        private string $filePath = '.hector.migrations.json',
    ) {
    }

    /**
     * @inheritDoc
     */
    protected function storageExists(): bool
    {
        return $this->filesystem->fileExists($this->filePath);
    }

    /**
     * @inheritDoc
     */
    protected function readStorage(): string
    {
        try {
            return $this->filesystem->read($this->filePath);
        } catch (UnableToReadFile $e) {
            throw new MigrationException(
                sprintf('Cannot read migration tracking file: %s', $this->filePath),
                previous: $e,
            );
        }
    }

    /**
     * @inheritDoc
     */
    protected function writeStorage(string $json): void
    {
        try {
            $this->filesystem->write($this->filePath, $json);
        } catch (UnableToWriteFile $e) {
            throw new MigrationException(
                sprintf('Cannot write migration tracking file: %s', $this->filePath),
                previous: $e,
            );
        }
    }
}
