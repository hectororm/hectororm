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

namespace Hector\Pagination\Tests\Encoder;

use Hector\Pagination\Encoder\Base64CursorEncoder;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class Base64CursorEncoderTest extends TestCase
{
    private Base64CursorEncoder $encoder;

    protected function setUp(): void
    {
        $this->encoder = new Base64CursorEncoder();
    }

    public function testEncode(): void
    {
        $position = ['id' => 42, 'created_at' => '2024-01-01'];

        $encoded = $this->encoder->encode($position);

        $this->assertIsString($encoded);
        $this->assertNotEmpty($encoded);
    }

    public function testDecode(): void
    {
        $position = ['id' => 42, 'created_at' => '2024-01-01'];
        $encoded = $this->encoder->encode($position);

        $decoded = $this->encoder->decode($encoded);

        $this->assertSame($position, $decoded);
    }

    public function testRoundTrip(): void
    {
        $positions = [
            ['id' => 1],
            ['id' => 100, 'name' => 'test'],
            ['a' => 1, 'b' => 2, 'c' => 3],
            ['nested' => ['foo' => 'bar']],
        ];

        foreach ($positions as $position) {
            $encoded = $this->encoder->encode($position);
            $decoded = $this->encoder->decode($encoded);

            $this->assertSame($position, $decoded);
        }
    }

    public function testDecodeInvalidBase64(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('base64 decode failed');

        $this->encoder->decode('!!!invalid-base64!!!');
    }

    public function testDecodeInvalidJson(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('JSON decode failed');

        $this->encoder->decode(base64_encode('not-json'));
    }

    public function testDecodeNonArrayJson(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('expected array');

        $this->encoder->decode(base64_encode('"string"'));
    }
}
