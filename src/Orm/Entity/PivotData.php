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

declare(strict_types=1);

namespace Hector\Orm\Entity;

class PivotData
{
    public const PIVOT_KEY_PREFIX = 'PIVOT_KEY_';
    public const PIVOT_DATA_PREFIX = 'PIVOT_DATA_';

    public function __construct(
        private array $keys = [],
        private array $data = [],
    ) {
    }

    /**
     * Get pivot keys.
     *
     * @return array
     */
    public function getKeys(): array
    {
        return $this->keys;
    }

    /**
     * Get pivot additional data.
     *
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Set pivot additional data.
     *
     * @param array $data
     * @param bool $replace
     */
    public function setData(array $data, bool $replace = true): void
    {
        if (true === $replace) {
            $this->data = $data;
            return;
        }

        $this->data = array_replace($this->data, $data);
    }

    /**
     * Array extract.
     *
     * @param array $data
     * @param string $prefix
     *
     * @return array
     */
    public static function extractPrefixedData(array $data, string $prefix): array
    {
        $data = array_filter(
            $data,
            fn($key): bool => str_starts_with($key, $prefix),
            ARRAY_FILTER_USE_KEY
        );

        $keys = array_keys($data);
        array_walk($keys, fn(&$key): string => $key = substr($key, strlen($prefix)));

        return array_combine($keys, array_values($data));
    }
}
