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

namespace Hector\DataTypes\Type;

use BackedEnum;
use Hector\DataTypes\Exception\ValueException;
use Hector\DataTypes\ExpectedType;
use ValueError;

class EnumType extends AbstractType
{
    public function __construct(protected string $enum, protected bool $try = false)
    {
        if (false === is_a($this->enum, BackedEnum::class, true)) {
            throw new ValueError('Enum must be a PHP 8.1 backed enum type');
        }
    }

    /**
     * @inheritDoc
     */
    public function fromSchema(mixed $value, ?ExpectedType $expected = null): mixed
    {
        $this->assertScalar($value);

        if (null !== $expected) {
            if ($expected->isBuiltin()) {
                if (!in_array($expected->getName(), ['int', 'string'])) {
                    throw ValueException::castError($this);
                }

                $value = $this->enum::{match ($this->try) {
                    false => 'from',
                    true => 'tryFrom',
                }}($value)?->value;

                if (null !== $value) {
                    settype($value, $expected->getName());
                }

                return $value;
            }

            if ($this->enum !== $expected->getName()) {
                ValueException::castError($this);
            }
        }

        return $this->enum::{match ($this->try) {
            false => 'from',
            true => 'tryFrom',
        }}($value);
    }

    /**
     * @inheritDoc
     */
    public function toSchema(mixed $value, ?ExpectedType $expected = null): mixed
    {
        if (false === is_a($value, $this->enum)) {
            if (is_scalar($value)) {
                return $this->enum::{match ($this->try) {
                    false => 'from',
                    true => 'tryFrom',
                }}($value)?->value;
            }

            throw new ValueError(sprintf('Value must be an enum "%s"', $this->enum));
        }

        return $value?->value;
    }
}
