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

namespace Hector\Orm\Pagination;

use Hector\Orm\Entity\Entity;
use Hector\Orm\Query\Builder;
use Hector\Query\Pagination\QueryRangePaginator;
use Hector\Query\QueryBuilder;

/**
 * @template T of Entity
 * @extends QueryRangePaginator<T>
 */
class BuilderRangePaginator extends QueryRangePaginator
{
    public function __construct(
        Builder $builder,
        bool $withTotal = true,
    ) {
        parent::__construct($builder, $withTotal);
    }

    /**
     * @inheritDoc
     */
    protected function fetchItems(QueryBuilder $builder): array
    {
        /** @var Builder $builder */
        return $builder->all()->getArrayCopy();
    }
}
