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

namespace Hector\Orm\Tests\Mapper;

use Hector\Orm\Entity\Entity;
use Hector\Orm\Mapper\AbstractMapper;

class FakeAbstractMapper extends AbstractMapper
{
    /**
     * @inheritDoc
     */
    public function hydrateEntity(Entity $entity, array $data): void
    {
    }

    /**
     * @inheritDoc
     */
    public function collectEntity(Entity $entity, ?array $columns = null): array
    {
        return [];
    }
}