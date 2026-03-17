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

namespace Hector\Migration;

use Hector\Schema\Plan\Plan;

interface MigrationInterface
{
    /**
     * Apply the migration.
     *
     * @param Plan $plan
     *
     * @return void
     */
    public function up(Plan $plan): void;
}
