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

    private array $data = [];

    public function __construct(
        private array $keys = []
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
     * Get pivot data.
     *
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Set data.
     *
     * @param array $data
     */
    public function setData(array $data): void
    {
        $this->data = $data;
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
            fn($key) => str_starts_with($key, $prefix),
            ARRAY_FILTER_USE_KEY
        );

        $keys = array_keys($data);
        array_walk($keys, fn(&$key) => $key = substr($key, strlen($prefix)));

        return array_combine($keys, array_values($data));
    }
}