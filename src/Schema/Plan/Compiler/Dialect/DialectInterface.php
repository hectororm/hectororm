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

namespace Hector\Schema\Plan\Compiler\Dialect;

use Hector\Schema\Plan\AlterTable;
use Hector\Schema\Plan\AlterView;
use Hector\Schema\Plan\Compiler\CompilationContext;
use Hector\Schema\Plan\CreateTable;
use Hector\Schema\Plan\CreateTrigger;
use Hector\Schema\Plan\CreateView;
use Hector\Schema\Plan\DropTable;
use Hector\Schema\Plan\DropTrigger;
use Hector\Schema\Plan\DropView;
use Hector\Schema\Plan\MigrateData;
use Hector\Schema\Plan\Operation\AddForeignKey;
use Hector\Schema\Plan\Operation\DropForeignKey;
use Hector\Schema\Plan\OperationInterface;

/**
 * A dialect renders SQL statements for a specific DBMS family.
 *
 * The compiler owns the ordering/orchestration; the dialect owns the SQL. All
 * DBMS-specific knowledge (quoting, keywords, supported clauses, table rebuild)
 * lives behind this interface so a new DBMS is a single new implementation.
 */
interface DialectInterface
{
    /**
     * Driver names handled by this dialect.
     *
     * Used to filter RawStatement entries scoped to specific drivers. Values
     * must match those returned by Connection\Driver\DriverInfo::getDriver().
     *
     * @return string[]
     */
    public function driverNames(): array;

    /**
     * Whether the dialect can add or drop a foreign key through ALTER TABLE.
     *
     * When false (e.g. SQLite), standalone AddForeignKey/DropForeignKey
     * operations are not emitted on their own; such changes go through a
     * full table rebuild handled by the dialect's ALTER TABLE compilation.
     *
     * @return bool
     */
    public function supportsAlterForeignKey(): bool;

    /**
     * Whether foreign keys of a CreateTable are inlined into the CREATE TABLE
     * body instead of being emitted as separate ALTER TABLE post-operations.
     *
     * @return bool
     */
    public function inlinesCreateTableForeignKeys(): bool;

    /**
     * Compile the SQL to disable foreign key checks.
     *
     * @return string
     */
    public function compileDisableForeignKeyChecks(): string;

    /**
     * Compile the SQL to enable foreign key checks.
     *
     * @return string
     */
    public function compileEnableForeignKeyChecks(): string;

    /**
     * Compile a CREATE TABLE statement (columns, indexes and, per dialect, FKs).
     *
     * @param CreateTable $createTable
     *
     * @return iterable<string>
     */
    public function compileCreateTable(CreateTable $createTable): iterable;

    /**
     * Compile ALTER TABLE statements from an operation group.
     *
     * @param AlterTable         $alterTable
     * @param CompilationContext $context
     *
     * @return iterable<string>
     */
    public function compileAlterTable(AlterTable $alterTable, CompilationContext $context): iterable;

    /**
     * Compile a DROP TABLE statement.
     *
     * @param DropTable $dropTable
     *
     * @return string
     */
    public function compileDropTable(DropTable $dropTable): string;

    /**
     * Compile a CREATE VIEW statement.
     *
     * @param CreateView $createView
     *
     * @return iterable<string>
     */
    public function compileCreateView(CreateView $createView): iterable;

    /**
     * Compile an ALTER VIEW statement.
     *
     * @param AlterView $alterView
     *
     * @return iterable<string>
     */
    public function compileAlterView(AlterView $alterView): iterable;

    /**
     * Compile a DROP VIEW statement.
     *
     * @param DropView $dropView
     *
     * @return string
     */
    public function compileDropView(DropView $dropView): string;

    /**
     * Compile a CREATE TRIGGER statement.
     *
     * @param CreateTrigger $trigger
     *
     * @return string
     */
    public function compileCreateTrigger(CreateTrigger $trigger): string;

    /**
     * Compile a DROP TRIGGER statement.
     *
     * @param DropTrigger $trigger
     *
     * @return string
     */
    public function compileDropTrigger(DropTrigger $trigger): string;

    /**
     * Compile a MIGRATE DATA statement (INSERT INTO ... SELECT ...).
     *
     * @param MigrateData $migrateData
     *
     * @return string
     */
    public function compileMigrateData(MigrateData $migrateData): string;

    /**
     * Compile a standalone ADD FOREIGN KEY operation (from a group, Post pass).
     *
     * @param AddForeignKey $operation
     *
     * @return string
     */
    public function compileAddForeignKey(AddForeignKey $operation): string;

    /**
     * Compile a standalone DROP FOREIGN KEY operation (from a group, Pre pass).
     *
     * @param DropForeignKey $operation
     *
     * @return string
     */
    public function compileDropForeignKey(DropForeignKey $operation): string;

    /**
     * Compile a standalone sub-operation added directly to the plan
     * (not wrapped in a CreateTable/AlterTable group).
     *
     * @param OperationInterface $operation
     *
     * @return iterable<string>
     */
    public function compileStandaloneOperation(OperationInterface $operation): iterable;
}
