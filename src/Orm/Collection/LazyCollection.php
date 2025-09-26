<?php
/*
 * This file is part of Hector ORM.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2022 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

declare(strict_types=1);

namespace Hector\Orm\Collection;

use Closure;

/**
 * Lazy collection of {@see \Hector\Orm\Entity\Entity} objects.
 *
 * This class extends the generic {@see \Hector\Collection\LazyCollection} and adds
 * ORM‑specific behaviours (save, delete, refresh, relation loading, …).
 *
 * @template T of \Hector\Orm\Entity\Entity
 * @extends \Hector\Collection\LazyCollection<T>
 */
class LazyCollection extends \Hector\Collection\LazyCollection
{
    /**
     * @inheritDoc
     * @return Collection<T> The lazy collection instance.
     */
    protected function newDefault(iterable|Closure $iterable): Collection
    {
        return new Collection($iterable);
    }

    /**
     * @inheritDoc
     */
    public function contains(mixed $value, bool $strict = false): bool
    {
        return $this->collect()->contains($value, $strict);
    }
}
