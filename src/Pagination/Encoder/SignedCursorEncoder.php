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

use InvalidArgumentException;

class SignedCursorEncoder implements CursorEncoderInterface
{
    public function __construct(
        private CursorEncoderInterface $inner,
        private string $secret,
        private string $algo = 'sha256',
    ) {
    }

    /**
     * @inheritDoc
     */
    public function encode(array $position): string
    {
        $payload = $this->inner->encode($position);

        return $payload . '.' . $this->sign($payload);
    }

    /**
     * @inheritDoc
     */
    public function decode(string $cursor): array
    {
        $parts = explode('.', $cursor, 2);

        if (count($parts) !== 2) {
            throw new InvalidArgumentException('Invalid cursor: malformed structure');
        }

        [$payload, $signature] = $parts;

        if (!hash_equals($this->sign($payload), $signature)) {
            throw new InvalidArgumentException('Invalid cursor: signature mismatch');
        }

        return $this->inner->decode($payload);
    }

    private function sign(string $payload): string
    {
        return hash_hmac($this->algo, $payload, $this->secret);
    }
}
