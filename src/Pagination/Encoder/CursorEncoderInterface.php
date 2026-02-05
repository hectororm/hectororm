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

interface CursorEncoderInterface
{
    /**
     * Encode cursor position to string.
     *
     * @param array<string, mixed> $position
     */
    public function encode(array $position): string;

    /**
     * Decode cursor string to position.
     *
     * @return array<string, mixed>
     */
    public function decode(string $cursor): array;
}
