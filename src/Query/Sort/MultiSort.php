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

namespace Hector\Query\Sort;

use Hector\Query\QueryBuilder;

final class MultiSort implements SortInterface
{
    /** @var SortInterface[] */
    private array $sorts;

    public function __construct(SortInterface ...$sorts)
    {
        $this->sorts = $sorts;
    }

    /**
     * Get sorts.
     *
     * @return SortInterface[]
     */
    public function getSorts(): array
    {
        return $this->sorts;
    }

    /**
     * @inheritDoc
     */
    public function apply(QueryBuilder $builder): void
    {
        foreach ($this->sorts as $sort) {
            $sort->apply($builder);
        }
    }
}
