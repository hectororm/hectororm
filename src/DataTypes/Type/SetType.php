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

use Hector\DataTypes\Exception\ValueException;
use Hector\DataTypes\ExpectedType;

class SetType extends AbstractType
{
    /**
     * @inheritDoc
     */
    public function fromSchema(mixed $value, ?ExpectedType $expected = null): mixed
    {
        if (!is_string($value)) {
            throw ValueException::castError($this);
        }

        // Explode string
        $value = explode(',', $value);
        $value = array_map('trim', $value);

        if (null !== $expected) {
            if ($expected->isBuiltin()) {
                if ('array' !== $expected->getName()) {
                    if ('string' !== $expected->getName()) {
                        throw ValueException::castError($this);
                    }

                    $value = implode(',', $value);
                }

                settype($value, $expected->getName());

                return $value;
            }

            throw ValueException::castNotBuiltin($this);
        }

        return (array)$value;
    }

    /**
     * @inheritDoc
     */
    public function toSchema(mixed $value, ?ExpectedType $expected = null): string
    {
        if (!is_array($value)) {
            throw ValueException::castError($this);
        }

        return implode(',', $value);
    }
}