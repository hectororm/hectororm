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

namespace Hector\Migration\Provider;

use Countable;
use Hector\Migration\MigrationInterface;
use IteratorAggregate;

/**
 * @extends IteratorAggregate<string, MigrationInterface>
 */
interface MigrationProviderInterface extends IteratorAggregate, Countable
{
    /**
     * Get all migrations as an array.
     *
     * @return array<string, MigrationInterface> Keyed by migration identifier
     */
    public function getArrayCopy(): array;
}
