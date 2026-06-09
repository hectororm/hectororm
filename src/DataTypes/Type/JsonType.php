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
use stdClass;
use Throwable;

class JsonType extends AbstractType
{
    public function __construct(private bool $associative = true)
    {
    }

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

        try {
            if (null !== $expected) {
                if ($expected->isBuiltin()) {
                    if ($expected->getName() == 'array') {
                        return json_decode($value, true, 512, JSON_THROW_ON_ERROR);
                    }

                    if ($expected->getName() == 'string') {
                        return (string)$value;
                    }
                }

                if ($expected->getName() == stdClass::class) {
                    return json_decode($value, false, 512, JSON_FORCE_OBJECT);
                }

                throw ValueException::castError($this);
            }

            return json_decode($value, $this->associative, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            throw ValueException::castError($this, $e);
        }
    }

    /**
     * @inheritDoc
     */
    public function toSchema(mixed $value, ?ExpectedType $expected = null): string|bool|null
    {
        if (null === $value) {
            return null;
        }

        try {
            return json_encode($value, JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            throw ValueException::castError($this, $e);
        }
    }

    /**
     * @inheritDoc
     */
    public function equals(mixed $entityData, mixed $schemaData): bool
    {
        if (parent::equals($entityData, $schemaData)) {
            return true;
        }

        if (null === $schemaData) {
            return empty($entityData);
        }

        // Normalize both sides to a decoded, key-sorted structure so the comparison
        // is insensitive to whitespace and to object/associative-array key ordering.
        return $this->normalize($entityData) === $this->normalize($schemaData);
    }

    /**
     * Normalize a value to a canonical, comparable representation.
     *
     * Strings are JSON-decoded (as associative arrays); already-decoded values are
     * kept as-is. Array keys are sorted recursively so that semantically-equal
     * structures with different key ordering compare as equal.
     *
     * @param mixed $value
     *
     * @return mixed
     */
    private function normalize(mixed $value): mixed
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = (null === $decoded && JSON_ERROR_NONE !== json_last_error()) ? $value : $decoded;
        }

        if (is_object($value)) {
            $value = (array)$value;
        }

        if (is_array($value)) {
            $value = array_map(fn(mixed $item): mixed => $this->normalize($item), $value);
            ksort($value);
        }

        return $value;
    }
}
