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

class IrreversibleMigration implements MigrationInterface
{
    public function up(Plan $plan): void
    {
        $plan->alter('users', function ($table) {
            $table->addColumn('avatar', 'VARCHAR(255)', nullable: true);
        });
    }
}
