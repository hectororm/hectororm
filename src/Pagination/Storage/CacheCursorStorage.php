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

namespace Hector\Pagination\Storage;

use DateInterval;
use Psr\SimpleCache\CacheInterface;

class CacheCursorStorage implements CursorStorageInterface
{
    private const PREFIX = 'hector_cursor_';

    public function __construct(
        private CacheInterface $cache,
        private DateInterval|int|null $defaultTtl = null,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function store(array $state, DateInterval|int|null $ttl = null): string
    {
        $name = $this->generateName();

        $this->cache->set(
            $this->buildKey($name),
            $state,
            $ttl ?? $this->defaultTtl,
        );

        return $name;
    }

    /**
     * @inheritDoc
     */
    public function retrieve(string $name): ?array
    {
        $state = $this->cache->get($this->buildKey($name));

        if (!is_array($state)) {
            return null;
        }

        return $state;
    }

    /**
     * @inheritDoc
     */
    public function delete(string $name): void
    {
        $this->cache->delete($this->buildKey($name));
    }

    private function generateName(): string
    {
        return bin2hex(random_bytes(16));
    }

    private function buildKey(string $name): string
    {
        return self::PREFIX . $name;
    }
}
