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

namespace Hector\Orm\Assert;

use Hector\Orm\Exception\RelationException;

trait MorphAssert
{
    /**
     * Assert explicit column mappings: polymorphic columns carry no foreign key to infer them from.
     *
     * @param string $name Relationship name
     * @param array ...$columns
     *
     * @throws RelationException
     */
    protected function assertMorphColumns(string $name, array ...$columns): void
    {
        foreach ($columns as $mapping) {
            if ([] === $mapping) {
                throw new RelationException(sprintf('Polymorphic relationship "%s" requires explicit columns', $name));
            }
        }
    }

    /**
     * Assert a discriminator mapping column names, other than key columns, to scalar values.
     *
     * @param string $name Relationship name
     * @param array $discriminator
     * @param string[] $keyColumns Key columns of the table holding the discriminator
     *
     * @throws RelationException
     */
    protected function assertDiscriminator(string $name, array $discriminator, array $keyColumns): void
    {
        if ([] === $discriminator) {
            throw new RelationException(sprintf('Polymorphic relationship "%s" requires a discriminator', $name));
        }

        foreach ($discriminator as $column => $value) {
            if (false === is_string($column) || false === is_scalar($value)) {
                throw new RelationException(sprintf(
                    'Discriminator of relationship "%s" must map column names to scalar values',
                    $name,
                ));
            }

            if (true === in_array($column, $keyColumns, true)) {
                throw new RelationException(sprintf(
                    'Discriminator column "%s" of relationship "%s" is already a key column',
                    $column,
                    $name,
                ));
            }
        }
    }
}
