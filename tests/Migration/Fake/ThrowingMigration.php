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

namespace Hector\Migration\Tests\Fake;

use Hector\Migration\MigrationInterface;
use Hector\Schema\Plan\Plan;
use RuntimeException;

/**
 * A migration whose up() throws while building the Plan (i.e. user code fails before any SQL
 * is produced), used to verify that such failures are caught, logged, dispatched and wrapped
 * like execution failures.
 */
class ThrowingMigration implements MigrationInterface
{
    public const MESSAGE = 'boom while building the plan';

    public function up(Plan $plan): void
    {
        throw new RuntimeException(self::MESSAGE);
    }
}
