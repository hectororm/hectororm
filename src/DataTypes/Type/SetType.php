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
        if (null === $value) {
            $this->assertNullable($expected);

            return null;
        }

        if (!is_string($value)) {
            throw ValueException::castError($this);
        }

        // Explode the comma-separated members. An empty SET yields an empty list
        // (explode('', ...) would otherwise produce a single empty member).
        $value = array_values(
            array_filter(
                array_map('trim', explode(',', $value)),
                static fn(string $member): bool => '' !== $member,
            )
        );

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

        return $value;
    }

    /**
     * @inheritDoc
     */
    public function toSchema(mixed $value, ?ExpectedType $expected = null): ?string
    {
        if (null === $value) {
            return null;
        }

        // A SET property may be typed as either an array of members or the raw
        // comma-separated string; accept both so the round-trip is symmetric with
        // fromSchema().
        if (is_string($value)) {
            return $value;
        }

        if (!is_array($value)) {
            throw ValueException::castError($this);
        }

        return implode(',', $value);
    }

    /**
     * @inheritDoc
     */
    public function equals(mixed $entityData, mixed $schemaData): bool
    {
        if (parent::equals($entityData, $schemaData)) {
            return true;
        }

        $entityData = $this->normalizeSet($entityData);
        $schemaData = $this->normalizeSet($schemaData);

        return empty(array_diff($entityData, $schemaData)) && empty(array_diff($schemaData, $entityData));
    }

    /**
     * Normalize a SET value to a list of members.
     *
     * Arrays are returned as-is; other values are cast to string and split on commas.
     * Empty members (e.g. produced by `explode(',', '')`) are removed so that an empty
     * string and an empty array are treated as the same empty set.
     *
     * @param mixed $value
     *
     * @return array
     */
    private function normalizeSet(mixed $value): array
    {
        if (!is_array($value)) {
            $value = explode(',', (string)($value ?? ''));
        }

        return array_values(array_filter($value, static fn(mixed $item): bool => '' !== (string)$item));
    }
}
