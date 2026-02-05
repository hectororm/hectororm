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
use JsonException;

class Base64CursorEncoder implements CursorEncoderInterface
{
    /**
     * @inheritDoc
     */
    public function encode(array $position): string
    {
        return base64_encode(json_encode($position, JSON_THROW_ON_ERROR));
    }

    /**
     * @inheritDoc
     */
    public function decode(string $cursor): array
    {
        $decoded = base64_decode($cursor, true);

        if (false === $decoded) {
            throw new InvalidArgumentException('Invalid cursor: base64 decode failed');
        }

        try {
            $position = json_decode($decoded, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new InvalidArgumentException('Invalid cursor: JSON decode failed', 0, $e);
        }

        if (!is_array($position)) {
            throw new InvalidArgumentException('Invalid cursor: expected array');
        }

        return $position;
    }
}
