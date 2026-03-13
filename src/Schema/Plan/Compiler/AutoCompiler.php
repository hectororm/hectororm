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

use Hector\Connection\Connection;
use Hector\Schema\Exception\PlanException;
use Hector\Schema\Plan\Plan;
use Hector\Schema\Schema;

final class AutoCompiler implements CompilerInterface
{
    private ?CompilerInterface $resolved = null;

    public function __construct(
        private Connection $connection,
    ) {
    }

    /**
     * Resolve the actual compiler based on the connection driver.
     *
     * @return CompilerInterface
     * @throws PlanException
     */
    private function resolve(): CompilerInterface
    {
        $capabilities = $this->connection->getDriverInfo()->getCapabilities();

        return $this->resolved ??= match ($this->connection->getDriverInfo()->getDriver()) {
            'mysql', 'mariadb', 'vitess' => new MySQLCompiler($capabilities),
            'sqlite' => new SqliteCompiler($capabilities),
            default => throw new PlanException(sprintf(
                'Unsupported driver "%s" for plan compilation',
                $this->connection->getDriverInfo()->getDriver(),
            )),
        };
    }

    /**
     * @inheritDoc
     * @throws PlanException
     */
    public function compile(Plan $plan, ?Schema $schema = null): iterable
    {
        return $this->resolve()->compile($plan, $schema);
    }
}
