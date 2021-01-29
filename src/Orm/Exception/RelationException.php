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

namespace Hector\Orm\Exception;

/**
 * Class RelationException.
 *
 * @package Hector\Orm\Exception
 */
class RelationException extends OrmException
{
    /**
     * Not found.
     *
     * @param string $name
     * @param string $entity
     *
     * @return RelationException
     */
    public static function notFound(string $name, string $entity): RelationException
    {
        return new static(sprintf('Relationship "%s" not found in entity "%s"', $name, $entity));
    }

    /**
     * Not found.
     *
     * @param string $entity
     * @param string $foreignEntity
     *
     * @return RelationException
     */
    public static function notFoundBetween(string $entity, string $foreignEntity): RelationException
    {
        return new static(sprintf('Relationship not found between "%s" and "%s"', $entity, $foreignEntity));
    }

    /**
     * Not attempted columns.
     *
     * @param string $name
     * @param array $columns
     * @param string $entity
     *
     * @return RelationException
     */
    public static function notAttemptedColumns(string $name, array $columns, string $entity): RelationException
    {
        return new static(
            sprintf('Entity "%s" does not have the same attempted columns (%s) for the relationship "%s"', $entity, implode(', ', $columns), $name)
        );
    }

    /**
     * Ambiguous relationship.
     *
     * @param string $entity
     * @param string $foreignEntity
     *
     * @return RelationException
     */
    public static function ambiguous(string $entity, string $foreignEntity): RelationException
    {
        return new static(sprintf('Ambiguous relationship between "%s" and "%s"', $entity, $foreignEntity));
    }
}