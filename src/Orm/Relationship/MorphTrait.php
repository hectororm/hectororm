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

use Hector\Orm\Assert\MorphAssert;
use Hector\Orm\Exception\RelationException;
use Hector\Orm\Query\Builder;

trait MorphTrait
{
    use MorphAssert;

    /** @var array<string, int|string> */
    private array $discriminator = [];

    /**
     * @inheritDoc
     */
    public function getDiscriminator(): array
    {
        return $this->discriminator;
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
            'Polymorphic relationship "%s.%s" cannot be inverted as "%s.%s"; declare MorphTo instead',
            $this->getSourceEntity(),
            $this->name,
            $this->getTargetEntity(),
            $name,
        ));
    }
}
