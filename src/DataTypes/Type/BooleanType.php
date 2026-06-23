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

class BooleanType extends AbstractType
{
    /**
     * @inheritDoc
     */
    public function fromSchema(mixed $value, ?ExpectedType $expected = null): mixed
    {
        if (null === $value) {
            $this->assertNullable($expected);

            return null;
        }

        $this->assertScalar($value);

        // Resolve textual booleans (e.g. the MySQL strings "true"/"false") before
        // any cast: a naive (bool) "false" would otherwise yield true.
        $value = $this->normalizeBoolean($value);

        if (null !== $expected) {
            if ($expected->isBuiltin()) {
                settype($value, $expected->getName());

                return $value;
            }

            throw ValueException::castNotBuiltin($this);
        }

        return $value;
    }

    /**
     * @inheritDoc
     */
    public function toSchema(mixed $value, ?ExpectedType $expected = null): ?int
    {
        if (null === $value) {
            return null;
        }

        $this->assertScalar($value);

        return (int)$this->normalizeBoolean($value);
    }

    /**
     * Resolve a scalar to a boolean, honouring the textual values "true"/"false"
     * (case-insensitive) so that the string "false" is not interpreted as true.
     */
    private function normalizeBoolean(mixed $value): bool
    {
        if (is_string($value)) {
            switch (strtolower($value)) {
                case 'true':
                    return true;
                case 'false':
                    return false;
            }
        }

        return (bool)$value;
    }
}
