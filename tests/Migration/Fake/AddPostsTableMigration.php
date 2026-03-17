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

use Hector\Migration\ReversibleMigrationInterface;
use Hector\Schema\Plan\Plan;

class AddPostsTableMigration implements ReversibleMigrationInterface
{
    public function up(Plan $plan): void
    {
        $plan->create('posts', function ($table) {
            $table->addColumn('id', 'INTEGER', autoIncrement: true);
            $table->addColumn('title', 'VARCHAR(255)');
            $table->addColumn('user_id', 'INTEGER');
            $table->addIndex('primary', ['id'], type: 'primary');
        });
    }

    public function down(Plan $plan): void
    {
        $plan->drop('posts');
    }
}
