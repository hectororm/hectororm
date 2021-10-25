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

namespace Hector\Orm\Relationship;

use Hector\Orm\Collection\Collection;
use Hector\Orm\Entity\Entity;
use Hector\Orm\Entity\ReflectionEntity;

trait ValidToManyTrait
{
    protected ReflectionEntity $targetEntity;

    /**
     * Valid related entity.
     *
     * @param Entity|Collection|null $related
     *
     * @return bool
     */
    public function valid(Entity|Collection|null $related): bool
    {
        if (null === $related) {
            return true;
        }

        if (!$related instanceof Collection) {
            return false;
        }

        if ($related->getAcceptedEntity() === Entity::class) {
            /** @var Entity $entity */
            foreach ($related as $entity) {
                if (get_class($entity) !== $this->targetEntity->getName()) {
                    return false;
                }
            }

            return true;
        }

        return is_a($related->getAcceptedEntity(), $this->targetEntity->getName(), true);
    }
}