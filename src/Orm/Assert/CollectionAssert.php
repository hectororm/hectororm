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

use Hector\Orm\Collection\Collection;
use TypeError;

trait CollectionAssert
{
    /**
     * Assert collection.
     *
     * @param Collection|string $collection
     */
    protected function assertCollection(Collection|string $collection): void
    {
        if (!is_string($collection)) {
            return;
        }

        if (!is_a($collection, Collection::class, true)) {
            throw new TypeError(sprintf('Excepted %s class, got %s', Collection::class, $collection));
        }
    }

    /**
     * Assert collection type.
     *
     * @param Collection|string $collection
     * @param Collection|string $expected
     */
    protected function assertCollectionType(Collection|string $collection, Collection|string $expected): void
    {
        $this->assertCollection($collection);

        if (false === is_a($collection, $expected, true)) {
            throw new TypeError(
                sprintf(
                    'Excepted %s collection, got %s',
                    is_string($expected) ? $expected : $expected::class,
                    is_string($collection) ? $collection : $collection::class,
                )
            );
        }
    }
}