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

use Hector\Schema\Plan\ObjectPlan;
use Hector\Schema\Schema;

interface CompilerInterface
{
    /**
     * Compile an object plan into SQL statements.
     *
     * When a schema is provided, the compiler may use it to introspect the database
     * and adapt the compilation strategy (e.g., table rebuild for SQLite, index existence checks).
     * Without a schema, all operations are assumed to be natively supported.
     *
     * @param ObjectPlan $objectPlan
     * @param Schema|null $schema
     *
     * @return iterable<string>
     */
    public function compile(ObjectPlan $objectPlan, ?Schema $schema = null): iterable;
}
