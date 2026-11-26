<?php
/*
 * This file is part of Hector ORM.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2026 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

declare(strict_types=1);

namespace Hector\DataTypes\Type;

use DateTimeZone;

/**
 * Type converting date/time values stored without timezone.
 *
 * The storage timezone is known by the layer using the type (e.g. the connection
 * of an entity), not by the type itself: it is given after the construction.
 */
interface StorageTimezoneAwareInterface extends TypeInterface
{
    /**
     * Get a type reading and writing the stored values in the given timezone.
     *
     * The type is not modified: a new one is returned.
     *
     * @param DateTimeZone $timezone
     *
     * @return static
     */
    public function withStorageTimezone(DateTimeZone $timezone): static;
}
