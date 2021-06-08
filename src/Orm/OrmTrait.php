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

namespace Hector\Orm;

use Hector\Orm\Exception\OrmException;

/**
 * Trait OrmTrait.
 */
trait OrmTrait
{
    public static ?Orm $orm = null;

    /**
     * Get Orm instance.
     *
     * @return Orm
     * @throws OrmException
     */
    public static function getOrm(): Orm
    {
        return static::$orm ?? throw new OrmException('ORM not initialized');
    }
}