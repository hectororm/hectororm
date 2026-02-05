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

namespace Hector\Pagination\Encoder;

use RuntimeException;
use SodiumException;

final class EncryptedCursorEncoder implements CursorEncoderInterface
{
    public function __construct(
        private string $key,
    ) {
        extension_loaded('sodium') || throw new RuntimeException('Sodium extension is required');

        if (strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw new RuntimeException(
                sprintf('Key must be exactly %d bytes', SODIUM_CRYPTO_SECRETBOX_KEYBYTES)
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function encode(array $position): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $encrypted = sodium_crypto_secretbox(
            json_encode($position, JSON_THROW_ON_ERROR),
            $nonce,
            $this->key
        );

        return sodium_bin2base64(
            $nonce . $encrypted,
            SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING
        );
    }

    /**
     * @inheritDoc
     */
    public function decode(string $cursor): array
    {
        try {
            $decoded = sodium_base642bin($cursor, SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
        } catch (SodiumException $e) {
            throw new RuntimeException('Decryption failed', previous: $e);
        }

        if (strlen($decoded) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES) {
            throw new RuntimeException('Invalid cursor format');
        }

        $nonce = substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        $decrypted = sodium_crypto_secretbox_open($ciphertext, $nonce, $this->key);

        if ($decrypted === false) {
            throw new RuntimeException('Decryption failed or cursor tampered');
        }

        return json_decode($decrypted, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Generate a secure key for this encoder.
     *
     * @return string
     */
    public static function generateKey(): string
    {
        return sodium_crypto_secretbox_keygen();
    }
}
