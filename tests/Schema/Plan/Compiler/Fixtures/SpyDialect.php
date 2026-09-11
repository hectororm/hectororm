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

namespace Hector\Schema\Tests\Plan\Compiler\Fixtures;

use Hector\Schema\Plan\AlterTable;
use Hector\Schema\Plan\AlterView;
use Hector\Schema\Plan\Compiler\CompilationContext;
use Hector\Schema\Plan\Compiler\Dialect\DialectInterface;
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
 * A minimal DialectInterface implementation for testing the Compiler's
 * ordering logic. Every method returns a short, identifiable marker instead of
 * real SQL, so tests can assert on statement ordering without SQL noise.
 */
final class SpyDialect implements DialectInterface
{
    public function __construct(
        private bool $supportsAlterForeignKey = true,
        private bool $inlinesCreateTableForeignKeys = false,
    ) {
    }

    public function driverNames(): array
    {
        return ['spy'];
    }

    public function supportsAlterForeignKey(): bool
    {
        return $this->supportsAlterForeignKey;
    }

    public function inlinesCreateTableForeignKeys(): bool
    {
        return $this->inlinesCreateTableForeignKeys;
    }

    public function compileDisableForeignKeyChecks(): string
    {
        return 'FK_OFF';
    }

    public function compileEnableForeignKeyChecks(): string
    {
        return 'FK_ON';
    }

    public function compileCreateTable(CreateTable $createTable): iterable
    {
        return ['CREATE:' . $createTable->getObjectName()];
    }

    public function compileAlterTable(AlterTable $alterTable, CompilationContext $context): iterable
    {
        return ['ALTER:' . $alterTable->getObjectName()];
    }

    public function compileDropTable(DropTable $dropTable): string
    {
        return 'DROP:' . $dropTable->getObjectName();
    }

    public function compileCreateView(CreateView $createView): iterable
    {
        return ['CREATE_VIEW:' . $createView->getObjectName()];
    }

    public function compileAlterView(AlterView $alterView): iterable
    {
        return ['ALTER_VIEW:' . $alterView->getObjectName()];
    }

    public function compileDropView(DropView $dropView): string
    {
        return 'DROP_VIEW:' . $dropView->getObjectName();
    }

    public function compileCreateTrigger(CreateTrigger $trigger): string
    {
        return 'CREATE_TRIGGER:' . $trigger->getName();
    }

    public function compileDropTrigger(DropTrigger $trigger): string
    {
        return 'DROP_TRIGGER:' . $trigger->getName();
    }

    public function compileMigrateData(MigrateData $migrateData): string
    {
        return 'MIGRATE:' . $migrateData->getObjectName();
    }

    public function compileAddForeignKey(AddForeignKey $operation): string
    {
        return 'ADD_FK:' . $operation->getName();
    }

    public function compileDropForeignKey(DropForeignKey $operation): string
    {
        return 'DROP_FK:' . $operation->getName();
    }

    public function compileStandaloneOperation(OperationInterface $operation): iterable
    {
        return ['STANDALONE:' . ($operation->getObjectName() ?? '?')];
    }
}
