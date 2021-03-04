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

namespace Hector\Connection;

use PDO;

/**
 * Class BindParam.
 *
 * @package Hector\Connection
 */
class BindParam
{
    private int $type;

    /**
     * BindParam constructor.
     *
     * @param mixed $variable
     * @param int|null $type
     */
    public function __construct(
        private mixed &$variable,
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
    public static function findDataType(mixed &$variable): int
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

        return PDO::PARAM_STR;
    }

    /**
     * Get variable.
     *
     * @return mixed
     */
    public function getVariable(): mixed
    {
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