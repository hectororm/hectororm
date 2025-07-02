<?php
/*
 * This file is part of Hector ORM.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2021 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

declare(strict_types=1);

namespace Hector\Orm\Assert;

use Hector\Orm\Entity\Entity;
use TypeError;

trait EntityAssert
{
    /**
     * Assert entity.
     *
     * @param Entity|string $entity
     */
    protected function assertEntity(Entity|string $entity): void
    {
        if (!is_string($entity)) {
            return;
        }

        if (!is_a($entity, Entity::class, true)) {
            throw new TypeError(sprintf('Excepted %s class, got %s', Entity::class, $entity));
        }
    }

    /**
     * Assert entity type.
     *
     * @param Entity|string $entity
     * @param Entity|string $expected
     */
    protected function assertEntityType(Entity|string $entity, Entity|string $expected): void
    {
        $this->assertEntity($entity);

        if (false === is_a($entity, $expected, true)) {
            throw new TypeError(
                sprintf(
                    'Excepted %s entity, got %s',
                    is_string($expected) ? $expected : $expected::class,
                    is_string($entity) ? $entity : $entity::class,
                )
            );
        }
    }
}