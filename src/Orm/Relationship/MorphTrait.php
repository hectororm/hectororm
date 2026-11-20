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

use Hector\Orm\Exception\RelationException;
use Hector\Orm\Query\Builder;

trait MorphTrait
{
    /** @var array<string, bool|float|int|string> */
    private array $discriminator = [];

    /**
     * @inheritDoc
     */
    public function getDiscriminator(): array
    {
        return $this->discriminator;
    }

    /**
     * A polymorphic relationship shares its key columns with other types: an inferred mapping would be wrong.
     *
     * @throws RelationException
     */
    private static function assertMorphColumns(string $name, array $columns): void
    {
        if ([] !== $columns) {
            return;
        }

        throw new RelationException(sprintf('Polymorphic relationship "%s" requires explicit columns', $name));
    }

    /**
     * @throws RelationException
     */
    private function setDiscriminator(array $discriminator): void
    {
        if ([] === $discriminator) {
            throw new RelationException(sprintf(
                'Polymorphic relationship "%s" requires a discriminator',
                $this->name,
            ));
        }

        foreach ($discriminator as $column => $value) {
            if (false === is_string($column) || false === is_scalar($value)) {
                throw new RelationException(sprintf(
                    'Discriminator of relationship "%s" must map column names to scalar values',
                    $this->name,
                ));
            }

            if (true === in_array($column, $this->getTargetColumns(), true)) {
                throw new RelationException(sprintf(
                    'Discriminator column "%s" of relationship "%s" is already a key column',
                    $column,
                    $this->name,
                ));
            }
        }

        $this->discriminator = $discriminator;
    }

    /**
     * The discriminator is structural: it is applied here so that resetWhere() cannot drop it.
     */
    protected function newBuilder(): Builder
    {
        return parent::newBuilder()->whereEquals($this->discriminator);
    }

    /**
     * @inheritDoc
     */
    public function reverse(string $name): Relationship
    {
        throw new RelationException(sprintf(
            'Polymorphic relationship "%s.%s" cannot be inverted as "%s.%s"',
            $this->getSourceEntity(),
            $this->name,
            $this->getTargetEntity(),
            $name,
        ));
    }
}
