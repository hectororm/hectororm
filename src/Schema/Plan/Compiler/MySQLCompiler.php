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

namespace Hector\Schema\Plan\Compiler;

use Hector\Connection\Driver\DriverCapabilities;
use Hector\Schema\Plan\Compiler\Dialect\MySQLDialect;

/**
 * MySQL / MariaDB / Vitess plan compiler.
 *
 * Thin convenience wrapper around {@see Compiler} configured with the
 * {@see MySQLDialect}. Kept for backward compatibility; new code may use
 * `new Compiler(new MySQLDialect($capabilities))` directly.
 */
final class MySQLCompiler extends Compiler
{
    public function __construct(?DriverCapabilities $capabilities = null)
    {
        parent::__construct(new MySQLDialect($capabilities));
    }
}
