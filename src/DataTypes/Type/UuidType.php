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
        if (strlen($value) === 16) {
            $value = bin2hex($value);
        }

        if (strlen($value) === 32) {
            $value = vsprintf(
                '%s%s-%s-%s-%s-%s%s%s',
                str_split($value, 4)
            );
        }

        return (string)$value;
    }

    /**
     * @inheritDoc
     */
    public function toSchema(mixed $value, ?ExpectedType $expected = null): string
    {
        if (!is_scalar($value)) {
            if (!$value instanceof Stringable) {
                throw ValueException::castError($this);
            }

            $value = (string)$value;
        }

        if (strlen($value) == 16) {
            $value = bin2hex($value);
        }

        // Remove dash
        $value = str_replace('-', '', $value);

        // To hexadecimal
        if ($this->storage == 'hexadecimal') {
            return $value;
        }

        // To binary
        if ($this->storage == 'binary') {
            return hex2bin($value);
        }

        return vsprintf(
            '%s%s-%s-%s-%s-%s%s%s',
            str_split($value, 4)
        );
    }
}
