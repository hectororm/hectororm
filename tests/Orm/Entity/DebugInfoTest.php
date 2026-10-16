<?php

declare(strict_types=1);

namespace Hector\Orm\Tests\Entity;

use Hector\Orm\Entity\Entity;
use Hector\Orm\Entity\EntityData;
use PHPUnit\Framework\TestCase;

class DebugInfoTest extends TestCase
{
    public function testDebugDumpDoesNotExposeEntityInternals(): void
    {
        $entity = new class extends Entity {
            public string $secret = 'private-value';
        };
        $data = new EntityData($entity);

        ob_start();
        try {
            var_dump($entity, $data, $data->getRelated());
            $output = ob_get_contents();
        } finally {
            ob_end_clean();
        }

        $this->assertIsString($output);
        $this->assertStringNotContainsString('private-value', $output);
        $this->assertStringNotContainsString('_hectorData', $output);
        $this->assertSame(3, substr_count($output, '(0)'));
    }
}
