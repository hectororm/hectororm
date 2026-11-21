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

namespace Hector\Orm\Relationship;

use Hector\Orm\Exception\OrmException;
use Hector\Orm\Exception\RelationException;

/**
 * Many-to-many relationship through a pivot table shared with other entities: a discriminator identifies its rows.
 */
class MorphToMany extends ManyToMany
{
    /**
     * Nothing can be inferred for a polymorphic pivot: its table and columns are always explicit.
     *
     * @param array<string, string> $columnsFrom Source column to pivot column
     * @param array<string, string> $columnsTo Pivot column to target column
     * @param array<string, bool|float|int|string> $discriminator Pivot column to the value identifying the rows
     *
     * @throws OrmException
     */
    public function __construct(
        string $name,
        string $sourceEntity,
        string $targetEntity,
        string $pivotTable,
        array $columnsFrom,
        array $columnsTo,
        array $discriminator,
    ) {
        if ([] === $columnsFrom || [] === $columnsTo) {
            throw new RelationException(sprintf('Polymorphic relationship "%s" requires explicit columns', $name));
        }

        if ([] === $discriminator) {
            throw new RelationException(sprintf('Polymorphic relationship "%s" requires a discriminator', $name));
        }

        parent::__construct($name, $sourceEntity, $targetEntity, $pivotTable, $columnsFrom, $columnsTo);

        foreach ($discriminator as $column => $value) {
            if (false === is_string($column) || false === is_scalar($value)) {
                throw new RelationException(sprintf(
                    'Discriminator of relationship "%s" must map column names to scalar values',
                    $name,
                ));
            }

            if (true === in_array($column, [...$this->pivotTargetColumns, ...$this->pivotSourceColumns], true)) {
                throw new RelationException(sprintf(
                    'Discriminator column "%s" of relationship "%s" is already a key column',
                    $column,
                    $name,
                ));
            }
        }

        $this->pivotDiscriminator = $discriminator;
    }

    /**
     * The opposite side reads and writes the same pivot rows.
     */
    public function reverse(string $name): Relationship
    {
        return new MorphToMany(
            $name,
            $this->getTargetEntity(),
            $this->getSourceEntity(),
            $this->pivotTable,
            array_combine($this->getTargetColumns(), $this->getPivotSourceColumns()),
            array_combine($this->getPivotTargetColumns(), $this->getSourceColumns()),
            $this->pivotDiscriminator,
        );
    }
}
