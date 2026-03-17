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

namespace Hector\Migration\Event;

/**
 * Dispatched before a migration is executed.
 *
 * Stoppable: if a listener calls stopPropagation(), the migration is skipped
 * (not executed and not tracked).
 */
class MigrationBeforeEvent extends AbstractMigrationEvent implements StoppableEventInterface
{
    use StoppableEventTrait;
}
