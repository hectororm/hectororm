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

namespace Hector\Orm\Exception;

use Hector\Orm\Entity\Entity;

/**
 * The versioned entity was modified or deleted since the expected version was read.
 */
class OptimisticLockException extends OrmException
{
    public function __construct(private Entity $entity, private int $expectedVersion)
    {
        parent::__construct(
            sprintf(
                'Entity "%s" was modified or deleted concurrently, expected version %d no longer matches',
                $entity::class,
                $expectedVersion,
            )
        );
    }

    /**
     * Get the entity in conflict.
     *
     * @return Entity
     */
    public function getEntity(): Entity
    {
        return $this->entity;
    }

    /**
     * Get the version the write was conditioned on.
     *
     * @return int
     */
    public function getExpectedVersion(): int
    {
        return $this->expectedVersion;
    }
}
