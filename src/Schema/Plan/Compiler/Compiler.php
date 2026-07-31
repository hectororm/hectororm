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

use Hector\Schema\Plan\AlterTable;
use Hector\Schema\Plan\AlterView;
use Hector\Schema\Plan\Compiler\Dialect\DialectInterface;
use Hector\Schema\Plan\CreateTable;
use Hector\Schema\Plan\CreateTrigger;
use Hector\Schema\Plan\CreateView;
use Hector\Schema\Plan\DisableForeignKeyChecks;
use Hector\Schema\Plan\DropTable;
use Hector\Schema\Plan\DropTrigger;
use Hector\Schema\Plan\DropView;
use Hector\Schema\Plan\EnableForeignKeyChecks;
use Hector\Schema\Plan\MigrateData;
use Hector\Schema\Plan\Operation\AddForeignKey;
use Hector\Schema\Plan\Operation\DropForeignKey;
use Hector\Schema\Plan\Operation\PostOperationInterface;
use Hector\Schema\Plan\Operation\PreOperationInterface;
use Hector\Schema\Plan\OperationGroupInterface;
use Hector\Schema\Plan\OperationInterface;
use Hector\Schema\Plan\Plan;
use Hector\Schema\Plan\RawStatement;
use Hector\Schema\Schema;

/**
 * Ordering-only plan compiler.
 *
 * Owns the *when* (operation ordering across three passes) and delegates the
 * *how* (SQL syntax) entirely to a {@see DialectInterface}. It is stateless:
 * all per-run state travels in a {@see CompilationContext}.
 */
class Compiler implements CompilerInterface
{
    /** Pre pass: DisableForeignKeyChecks, DROP FOREIGN KEY, DROP TRIGGER. */
    private const PHASE_PRE = 'pre';
    /** Structure pass: CREATE/ALTER/DROP, raw statements, in declaration order. */
    private const PHASE_STRUCTURE = 'structure';
    /** Post pass: ADD FOREIGN KEY, CREATE TRIGGER, EnableForeignKeyChecks. */
    private const PHASE_POST = 'post';

    public function __construct(
        private readonly DialectInterface $dialect,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function compile(Plan $plan, ?Schema $schema = null): iterable
    {
        return $this->compilePlan($plan, new CompilationContext($schema, $this->detectManagedForeignKeyChecks($plan)));
    }

    /**
     * Compile a plan with an explicit context.
     *
     * Reentrant entry point used both by {@see compile()} and by dialects that
     * need to compile an inner plan (e.g. the SQLite table rebuild) while
     * preserving the current foreign-key-check management state.
     *
     * @param Plan               $plan
     * @param CompilationContext $context
     *
     * @return iterable<string>
     */
    public function compilePlan(Plan $plan, CompilationContext $context): iterable
    {
        yield from $this->compilePhase($plan, $context, self::PHASE_PRE);
        yield from $this->compilePhase($plan, $context, self::PHASE_STRUCTURE);
        yield from $this->compilePhase($plan, $context, self::PHASE_POST);
    }

    /**
     * Emit every statement belonging to a given phase.
     *
     * Autonomous entries are matched against the phase; group entries are
     * unrolled so their Pre/Post sub-operations land in the right phase while
     * the group body itself is compiled once, during the structure phase.
     *
     * @param Plan               $plan
     * @param CompilationContext $context
     * @param string             $phase
     *
     * @return iterable<string>
     */
    private function compilePhase(Plan $plan, CompilationContext $context, string $phase): iterable
    {
        foreach ($plan as $entry) {
            if ($entry instanceof OperationGroupInterface) {
                yield from $this->compileGroupForPhase($entry, $context, $phase);
                continue;
            }

            if ($this->phaseOf($entry) === $phase) {
                yield from $this->compileOperation($entry, $context);
            }
        }
    }

    /**
     * Emit the parts of a group that belong to the given phase.
     *
     * @param OperationGroupInterface $group
     * @param CompilationContext      $context
     * @param string                  $phase
     *
     * @return iterable<string>
     */
    private function compileGroupForPhase(
        OperationGroupInterface $group,
        CompilationContext $context,
        string $phase,
    ): iterable {
        // The group body (CREATE/ALTER TABLE) is compiled once, in the structure phase.
        if (self::PHASE_STRUCTURE === $phase) {
            yield from $this->compileOperation($group, $context);

            return;
        }

        $inlineForeignKeys = self::PHASE_POST === $phase
            && $group instanceof CreateTable
            && $this->dialect->inlinesCreateTableForeignKeys();

        foreach ($group as $operation) {
            if ($this->phaseOf($operation) !== $phase) {
                continue;
            }

            // FKs of a CreateTable are inlined into the body by some dialects
            // (e.g. SQLite), so they must not be re-emitted here.
            if ($inlineForeignKeys && $operation instanceof AddForeignKey) {
                continue;
            }

            yield from $this->compileOperation($operation, $context);
        }
    }

    /**
     * Determine which phase an operation belongs to.
     *
     * @param OperationInterface $operation
     *
     * @return string
     */
    private function phaseOf(OperationInterface $operation): string
    {
        return match (true) {
            $operation instanceof PreOperationInterface => self::PHASE_PRE,
            $operation instanceof PostOperationInterface => self::PHASE_POST,
            default => self::PHASE_STRUCTURE,
        };
    }

    /**
     * Compile a single operation by delegating the SQL to the dialect.
     *
     * @param OperationInterface $operation
     * @param CompilationContext $context
     *
     * @return iterable<string>
     */
    private function compileOperation(OperationInterface $operation, CompilationContext $context): iterable
    {
        // Foreign-key operations that the dialect cannot express through ALTER TABLE
        // (e.g. SQLite) are skipped here; such changes go through a table rebuild.
        if (
            false === $this->dialect->supportsAlterForeignKey()
            && ($operation instanceof AddForeignKey || $operation instanceof DropForeignKey)
        ) {
            return;
        }

        switch (true) {
            case $operation instanceof DisableForeignKeyChecks:
                yield $this->dialect->compileDisableForeignKeyChecks();
                break;
            case $operation instanceof EnableForeignKeyChecks:
                yield $this->dialect->compileEnableForeignKeyChecks();
                break;
            case $operation instanceof RawStatement:
                yield from $this->compileRawStatement($operation);
                break;
            case $operation instanceof CreateTable:
                yield from $this->dialect->compileCreateTable($operation);
                break;
            case $operation instanceof AlterTable:
                yield from $this->dialect->compileAlterTable($operation, $context);
                break;
            case $operation instanceof DropTable:
                yield $this->dialect->compileDropTable($operation);
                break;
            case $operation instanceof MigrateData:
                yield $this->dialect->compileMigrateData($operation);
                break;
            case $operation instanceof CreateView:
                yield from $this->dialect->compileCreateView($operation);
                break;
            case $operation instanceof AlterView:
                yield from $this->dialect->compileAlterView($operation);
                break;
            case $operation instanceof DropView:
                yield $this->dialect->compileDropView($operation);
                break;
            case $operation instanceof CreateTrigger:
                yield $this->dialect->compileCreateTrigger($operation);
                break;
            case $operation instanceof DropTrigger:
                yield $this->dialect->compileDropTrigger($operation);
                break;
            case $operation instanceof AddForeignKey:
                yield $this->dialect->compileAddForeignKey($operation);
                break;
            case $operation instanceof DropForeignKey:
                yield $this->dialect->compileDropForeignKey($operation);
                break;
            default:
                // Sub-operation added directly to the plan (not wrapped in a group).
                if (false === ($operation instanceof OperationGroupInterface)) {
                    yield from $this->dialect->compileStandaloneOperation($operation);
                }
        }
    }

    /**
     * Emit a raw statement if it targets this dialect's driver(s).
     *
     * @param RawStatement $operation
     *
     * @return iterable<string>
     */
    private function compileRawStatement(RawStatement $operation): iterable
    {
        $drivers = $operation->getDrivers();

        if (null === $drivers || [] !== array_intersect($drivers, $this->dialect->driverNames())) {
            yield $operation->getStatement();
        }
    }

    /**
     * Whether the plan explicitly manages foreign key checks.
     *
     * @param Plan $plan
     *
     * @return bool
     */
    private function detectManagedForeignKeyChecks(Plan $plan): bool
    {
        foreach ($plan as $entry) {
            if ($entry instanceof DisableForeignKeyChecks) {
                return true;
            }
        }

        return false;
    }
}
