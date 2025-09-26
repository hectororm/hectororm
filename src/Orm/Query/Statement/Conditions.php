<?php
/*
 * This file is part of Hector ORM.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2023 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

declare(strict_types=1);

namespace Hector\Orm\Query\Statement;

use Hector\Orm\Entity\ReflectionEntity;
use Hector\Orm\Query\Builder;

class Conditions extends \Hector\Query\Statement\Conditions
{
    public function __construct(
        Builder $builder,
        private ReflectionEntity $entityReflection,
    ) {
        parent::__construct($builder);
    }

    /**
     * @inheritDoc
     */
    public function resetWhere(): static
    {
        $this->where = new \Hector\Orm\Query\Component\Conditions($this->builder, $this->entityReflection, $this);

        return $this;
    }
}
