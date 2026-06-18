<?php
/*
 * This file is part of Hector ORM.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2021 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

namespace Hector\Orm\Tests;

use Hector\Connection\Connection;
use Hector\Connection\Log\Logger;
use Hector\Orm\Orm;
use Hector\Orm\OrmFactory;
use LogicException;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

class OrmFactoryTest extends TestCase
{
    private ?Orm $previousInstance = null;

    protected function setUp(): void
    {
        parent::setUp();

        // OrmFactory::orm() builds a new Orm, which is a singleton; save and clear any
        // existing instance (e.g. the shared test ORM) so the factory can initialize one,
        // then restore it in tearDown to avoid contaminating other tests.
        $property = $this->ormInstanceProperty();
        $this->previousInstance = $property->getValue();
        $property->setValue(null, null);
    }

    protected function tearDown(): void
    {
        $this->ormInstanceProperty()->setValue(null, $this->previousInstance);
        parent::tearDown();
    }

    private function ormInstanceProperty(): ReflectionProperty
    {
        $property = new ReflectionProperty(Orm::class, 'instance');
        $property->setAccessible(true);

        return $property;
    }

    public function testOrmWithoutCache(): void
    {
        $connection = new Connection(
            dsn: getenv('MYSQL_DSN') ?: throw new LogicException('Missing env variable "MYSQL_DSN" for tests'),
            logger: new Logger()
        );

        // No cache argument: the factory must not fatal on $cache->set(...).
        $orm = OrmFactory::orm(['schemas' => ['sakila']], $connection);

        $this->assertInstanceOf(Orm::class, $orm);
    }
}
