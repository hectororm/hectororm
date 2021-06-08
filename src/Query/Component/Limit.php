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

namespace Hector\Query\Component;

/**
 * Class Limit.
 */
class Limit extends AbstractComponent
{
    private ?int $offset = null;
    private ?int $limit = null;

    /**
     * Get offset.
     *
     * @return int|null
     */
    public function getOffset(): ?int
    {
        return $this->offset;
    }

    /**
     * Set offset.
     *
     * @param int|null $offset
     */
    public function setOffset(?int $offset): void
    {
        $this->offset = $offset;
    }

    /**
     * Get limit.
     *
     * @return int|null
     */
    public function getLimit(): ?int
    {
        return $this->limit;
    }

    /**
     * Set limit.
     *
     * @param int|null $limit
     */
    public function setLimit(?int $limit): void
    {
        $this->limit = $limit;
    }

    /**
     * @inheritDoc
     */
    public function getStatement(array &$binding, bool $encapsulate = false): ?string
    {
        if (null === $this->limit) {
            return null;
        }

        if (null === $this->offset) {
            return sprintf('LIMIT %d', $this->limit) . PHP_EOL;
        }

        return sprintf('LIMIT %d OFFSET %d', $this->limit, $this->offset) . PHP_EOL;
    }
}