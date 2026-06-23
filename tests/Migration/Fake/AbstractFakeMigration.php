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

/**
 * An abstract migration: it implements MigrationInterface (so it passes is_subclass_of /
 * instanceof checks) but cannot be instantiated, used to verify the provider rejects it with a
 * clear exception instead of a raw Error from `new`.
 */
abstract class AbstractFakeMigration implements MigrationInterface
{
}
