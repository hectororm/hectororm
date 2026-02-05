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

use Hector\Pagination\Encoder\EncryptedCursorEncoder;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class EncryptedCursorEncoderTest extends TestCase
{
    private EncryptedCursorEncoder $encoder;
    private string $key;

    protected function setUp(): void
    {
        $this->key = EncryptedCursorEncoder::generateKey();
        $this->encoder = new EncryptedCursorEncoder($this->key);
    }

    public function testGenerateKey(): void
    {
        $key = EncryptedCursorEncoder::generateKey();

        $this->assertSame(SODIUM_CRYPTO_SECRETBOX_KEYBYTES, strlen($key));
    }

    public function testConstructorRejectsInvalidKeyLength(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('must be exactly');

        new EncryptedCursorEncoder('too-short');
    }

    public function testEncode(): void
    {
        $position = ['id' => 42];

        $encoded = $this->encoder->encode($position);

        $this->assertIsString($encoded);
        $this->assertNotEmpty($encoded);
        // URL-safe: no +, /, or =
        $this->assertDoesNotMatchRegularExpression('/[+\/=]/', $encoded);
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
            ['unicode' => 'été café'],
        ];

        foreach ($positions as $position) {
            $encoded = $this->encoder->encode($position);
            $decoded = $this->encoder->decode($encoded);

            $this->assertSame($position, $decoded);
        }
    }

    public function testEncodedCursorsAreUnique(): void
    {
        $position = ['id' => 42];

        $encoded1 = $this->encoder->encode($position);
        $encoded2 = $this->encoder->encode($position);

        // Different nonces = different ciphertexts
        $this->assertNotSame($encoded1, $encoded2);

        // But both decode to same value
        $this->assertSame($position, $this->encoder->decode($encoded1));
        $this->assertSame($position, $this->encoder->decode($encoded2));
    }

    public function testDecodeTamperedCursor(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Decryption failed');

        $encoded = $this->encoder->encode(['id' => 42]);
        $tampered = substr($encoded, 0, -5) . 'XXXXX';

        $this->encoder->decode($tampered);
    }

    public function testDecodeInvalidBase64(): void
    {
        $this->expectException(RuntimeException::class);

        $this->encoder->decode('!!!invalid!!!');
    }

    public function testDecodeTruncatedCursor(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid cursor format');

        $this->encoder->decode('dG9vLXNob3J0'); // "too-short" in base64
    }

    public function testDecodeWithWrongKey(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Decryption failed');

        $encoded = $this->encoder->encode(['id' => 42]);

        $otherEncoder = new EncryptedCursorEncoder(
            EncryptedCursorEncoder::generateKey()
        );
        $otherEncoder->decode($encoded);
    }
}
