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

class MorphMany extends OneToMany
{
    use MorphTrait;

    /**
     * @param array<string, string> $columns Source column to target column
     * @param array<string, bool|float|int|string> $discriminator Target column to the value identifying the source type
     *
     * @throws OrmException
     */
    public function __construct(
        string $name,
        string $sourceEntity,
        string $targetEntity,
        array $columns,
        array $discriminator,
        bool $orphanRemoval = false,
    ) {
        self::assertMorphColumns($name, $columns);

        parent::__construct($name, $sourceEntity, $targetEntity, $columns, $orphanRemoval);

        $this->setDiscriminator($discriminator);
    }
}
