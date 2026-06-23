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

use Hector\Migration\Tests\Fake\AbstractFakeMigration;

// Returns the FQCN of an abstract migration class, which the provider must reject as
// not instantiable rather than fail with a raw Error from `new`.
return AbstractFakeMigration::class;
