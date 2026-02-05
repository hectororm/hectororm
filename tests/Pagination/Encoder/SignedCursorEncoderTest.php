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
use Hector\Pagination\Encoder\SignedCursorEncoder;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class SignedCursorEncoderTest extends TestCase
{
    private SignedCursorEncoder $encoder;

    protected function setUp(): void
    {
        $this->encoder = new SignedCursorEncoder(
            new Base64CursorEncoder(),
            'my-secret-key',
        );
    }

    public function testEncode(): void
    {
        $position = ['id' => 42];

        $encoded = $this->encoder->encode($position);

        $this->assertIsString($encoded);
        $this->assertStringContainsString('.', $encoded);
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
        $position = ['a' => 1, 'b' => 'test', 'c' => true];

        $encoded = $this->encoder->encode($position);
        $decoded = $this->encoder->decode($encoded);

        $this->assertSame($position, $decoded);
    }

    public function testDecodeTamperedPayload(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('signature mismatch');

        $encoded = $this->encoder->encode(['id' => 42]);
        [$payload, $signature] = explode('.', $encoded);

        // Tamper with payload
        $tamperedPayload = base64_encode(json_encode(['id' => 999]));
        $tampered = $tamperedPayload . '.' . $signature;

        $this->encoder->decode($tampered);
    }

    public function testDecodeTamperedSignature(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('signature mismatch');

        $encoded = $this->encoder->encode(['id' => 42]);
        [$payload, $signature] = explode('.', $encoded);

        $tampered = $payload . '.invalidsignature';

        $this->encoder->decode($tampered);
    }

    public function testDecodeMalformedCursor(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('malformed structure');

        $this->encoder->decode('no-dot-separator');
    }

    public function testDecodeWrongSecret(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('signature mismatch');

        $encoded = $this->encoder->encode(['id' => 42]);

        $otherEncoder = new SignedCursorEncoder(
            new Base64CursorEncoder(),
            'different-secret',
        );
        $otherEncoder->decode($encoded);
    }

    public function testCustomAlgorithm(): void
    {
        $encoder = new SignedCursorEncoder(
            new Base64CursorEncoder(),
            'secret',
            'sha512',
        );
        $position = ['id' => 1];

        $encoded = $encoder->encode($position);
        $decoded = $encoder->decode($encoded);

        $this->assertSame($position, $decoded);
    }
}
