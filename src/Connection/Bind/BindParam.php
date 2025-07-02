<?php
/*
 * This file is part of Hector ORM.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2022 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

declare(strict_types=1);

namespace Hector\Connection\Bind;

use BackedEnum;
use PDO;

class BindParam
{
    private int $type;

    /**
     * BindParam constructor.
     *
     * @param int|string $name
     * @param mixed $variable
     * @param int|null $type
     */
    public function __construct(
        private int|string $name,
        private mixed $variable,
        ?int $type = null
    ) {
        $this->type = $type ?? static::findDataType($variable);
    }

    /**
     * Find data type.
     *
     * @param mixed $variable
     *
     * @return int
     */
    public static function findDataType(mixed $variable): int
    {
        if (is_resource($variable)) {
            return PDO::PARAM_LOB;
        }

        if (is_int($variable)) {
            return PDO::PARAM_INT;
        }

        if (is_bool($variable)) {
            return PDO::PARAM_BOOL;
        }

        if ($variable instanceof BackedEnum) {
            if (is_int($variable->value)) {
                return PDO::PARAM_INT;
            }
        }

        return PDO::PARAM_STR;
    }

    /**
     * Get name.
     *
     * @return int|string
     */
    public function getName(): int|string
    {
        return $this->name;
    }

    /**
     * Get value.
     *
     * @return mixed
     */
    public function getValue(): mixed
    {
        if ($this->variable instanceof BackedEnum) {
            return $this->variable->value;
        }

        return $this->variable;
    }

    /**
     * Get data type.
     *
     * @return int
     * @see PDO::PARAM_*
     */
    public function getDataType(): int
    {
        return $this->type;
    }
}