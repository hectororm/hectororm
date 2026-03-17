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

namespace Hector\Migration\Tracker;

final class ChainStrategy
{
    /** Migration is considered applied if ANY tracker reports it */
    public const ANY = 'any';

    /** Migration is considered applied only if ALL trackers report it */
    public const ALL = 'all';

    /** Only the first tracker is consulted for read operations */
    public const FIRST = 'first';

    private function __construct()
    {
    }
}
