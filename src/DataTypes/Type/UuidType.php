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
use InvalidArgumentException;
use Stringable;

class UuidType extends AbstractType
{
    private string $storage;

    public function __construct(string $storage = 'string')
    {
        $storage = strtolower($storage);

        if (false === in_array($storage, ['binary', 'hexadecimal', 'string'])) {
            throw new InvalidArgumentException('$storage argument must be: binary, hexadecimal, string');
        }

        $this->storage = $storage;
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

        $hex = $this->normalizeToHex((string)$value);

        return $this->formatHex($hex);
    }

    /**
     * @inheritDoc
     */
    public function toSchema(mixed $value, ?ExpectedType $expected = null): ?string
    {
        if (null === $value) {
            return null;
        }

        if (!is_scalar($value)) {
            if (!$value instanceof Stringable) {
                throw ValueException::castError($this);
            }

            $value = (string)$value;
        }

        $hex = $this->normalizeToHex((string)$value);

        // To hexadecimal
        if ('hexadecimal' === $this->storage) {
            return $hex;
        }

        // To binary
        if ('binary' === $this->storage) {
            return hex2bin($hex);
        }

        return $this->formatHex($hex);
    }

    /**
     * Normalize any accepted UUID representation to a canonical 32-character
     * lowercase hexadecimal string.
     *
     * Accepts a 16-byte binary string, a 32-character hexadecimal string and a
     * 36-character dashed UUID. Any other value is rejected so that malformed
     * data never reaches `hex2bin()`/`vsprintf()` (which would otherwise corrupt
     * the value or raise a raw warning/error).
     *
     * @throws ValueException
     */
    private function normalizeToHex(string $value): string
    {
        if (16 === strlen($value)) {
            $value = bin2hex($value);
        }

        // Remove dashes from a formatted UUID.
        $value = str_replace('-', '', $value);

        if (1 !== preg_match('/^[0-9a-f]{32}$/i', $value)) {
            throw ValueException::castError($this);
        }

        return strtolower($value);
    }

    /**
     * Format a 32-character hexadecimal string as a dashed UUID.
     */
    private function formatHex(string $hex): string
    {
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split($hex, 4));
    }
}
