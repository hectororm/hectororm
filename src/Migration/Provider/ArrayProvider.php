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

namespace Hector\Migration\Provider;

use ArrayIterator;
use Hector\Migration\Exception\MigrationException;
use Hector\Migration\MigrationInterface;

class ArrayProvider implements MigrationProviderInterface
{
    /** @var array<string, MigrationInterface> */
    private array $migrations;

    /**
     * ArrayProvider constructor.
     *
     * @param array<string, MigrationInterface> $migrations Keyed by migration identifier
     */
    public function __construct(array $migrations = [])
    {
        $this->migrations = $migrations;
    }

    /**
     * Add a migration.
     *
     * @param MigrationInterface $migration
     * @param string|null $id Migration identifier (defaults to FQCN)
     *
     * @return static
     * @throws MigrationException
     */
    public function add(MigrationInterface $migration, ?string $id = null): static
    {
        $id ??= $migration::class;

        if (true === array_key_exists($id, $this->migrations)) {
            throw new MigrationException(
                sprintf('Duplicate migration identifier "%s"', $id)
            );
        }

        $this->migrations[$id] = $migration;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getArrayCopy(): array
    {
        return $this->migrations;
    }

    /**
     * @inheritDoc
     *
     * @return ArrayIterator<string, MigrationInterface>
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->migrations);
    }

    /**
     * @inheritDoc
     */
    public function count(): int
    {
        return count($this->migrations);
    }
}
