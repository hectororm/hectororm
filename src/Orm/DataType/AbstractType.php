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

namespace Hector\Orm\DataType;

/**
 * Class AbstractType.
 *
 * @package Hector\Orm\DataType
 */
abstract class AbstractType implements TypeInterface
{
    public const NAME = null;

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return static::NAME;
    }

    /**
     * @inheritDoc
     */
    public function fromSchemaFunction(): ?string
    {
        return null;
    }

    /**
     * @inheritDoc
     */
    public function getBindingType(): ?int
    {
        return null;
    }
}