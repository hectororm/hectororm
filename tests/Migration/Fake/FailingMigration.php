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

/**
 * A migration that creates the 'users' table.
 *
 * When run against a database that already has a 'users' table,
 * the CREATE TABLE statement will fail, triggering a rollback.
 */
class FailingMigration implements MigrationInterface
{
    public function up(Plan $plan): void
    {
        $plan->create('users', function ($table) {
            $table->addColumn('id', 'INTEGER', autoIncrement: true);
            $table->addIndex('primary', ['id'], type: 'primary');
        });
    }
}
