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
use Hector\Schema\Plan\ObjectPlan;
use Hector\Schema\Schema;

class AutoCompiler implements CompilerInterface
{
    private ?CompilerInterface $resolved = null;

    public function __construct(
        private Connection $connection,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function compile(ObjectPlan $objectPlan, ?Schema $schema = null): iterable
    {
        return $this->resolve()->compile($objectPlan, $schema);
    }

    /**
     * Resolve the actual compiler based on the connection driver.
     *
     * @return CompilerInterface
     * @throws PlanException
     */
    private function resolve(): CompilerInterface
    {
        return $this->resolved ??= match ($this->connection->getDriverInfo()->getDriver()) {
            'mysql', 'mariadb', 'vitess' => new MySQLCompiler(),
            'sqlite' => new SqliteCompiler(),
            default => throw new PlanException(
                sprintf('Unsupported driver "%s" for plan compilation', $this->connection->getDriverInfo()->getDriver())
            ),
        };
    }
}
