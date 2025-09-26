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

namespace Hector\Orm\Event;

use Hector\Orm\Entity\Entity;

abstract class EntitySaveEvent extends EntityEvent
{
    /**
     * EntitySaveEvent constructor.
     *
     * @param Entity $entity
     * @param bool $isUpdate Is an update of existent entity?
     */
    public function __construct(Entity $entity, private bool $isUpdate = false)
    {
        parent::__construct($entity);
    }

    /**
     * Is an update?
     *
     * @return bool
     */
    public function isUpdate(): bool
    {
        return $this->isUpdate;
    }
}
