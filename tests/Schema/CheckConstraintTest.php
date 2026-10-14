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

namespace Hector\Schema\Tests;

use Hector\Schema\CheckConstraint;
use Hector\Schema\Exception\SchemaException;
use Hector\Schema\Table;
use PHPUnit\Framework\TestCase;

class CheckConstraintTest extends TestCase
{
    public static function providerConstraints(): array
    {
        return [
            'enforced named' => ['chk_price', true],
            'not enforced named' => ['chk_price', false],
            'unknown enforcement' => ['chk_price', null],
            'unnamed' => [null, true],
        ];
    }

    /**
     * @dataProvider providerConstraints
     */
    public function testMetadataSurvivesSerialization(?string $name, ?bool $enforced): void
    {
        $expression = "\n  price >= 0 /* keep spacing */ AND label <> 'CHECK (price)'\n";
        $check = new CheckConstraint($name, $expression, $enforced);
        $table = new Table('main', Table::TYPE_TABLE, 'products', checks: [$check]);

        self::assertSame($table, $check->getTable());
        self::assertSame($name, $check->getName());
        self::assertSame($expression, $check->getExpression());
        self::assertSame($enforced, $check->isEnforced());

        /** @var CheckConstraint $restored */
        $restored = unserialize(serialize($check));

        self::assertSame($name, $restored->getName());
        self::assertSame($expression, $restored->getExpression());
        self::assertSame($enforced, $restored->isEnforced());

        $this->expectException(SchemaException::class);
        $this->expectExceptionMessage('No table attached to the check constraint');
        $restored->getTable();
    }

    public function testUnknownEnforcementIsNotAssumedEnabled(): void
    {
        $check = new CheckConstraint(null, 'price >= 0');

        self::assertNull($check->isEnforced());
    }

    public function testConstraintCanBeDetached(): void
    {
        $check = new CheckConstraint('chk_price', 'price >= 0');
        $table = new Table('main', Table::TYPE_TABLE, 'products');
        $check->setTable($table);
        self::assertSame($table, $check->getTable());

        $check->setTable(null);

        $this->expectException(SchemaException::class);
        $check->getTable();
    }
}
