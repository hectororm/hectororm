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

namespace Hector\Schema\Plan;

final class CreateTable extends TableOperation
{
    /**
     * CreateTable constructor.
     *
     * @param string $name
     * @param string|null $charset
     * @param string|null $collation
     * @param bool $ifNotExists
     */
    public function __construct(
        string $name,
        private ?string $charset = null,
        private ?string $collation = null,
        private bool $ifNotExists = false,
    ) {
        parent::__construct($name);
    }

    /**
     * Get charset.
     *
     * @return string|null
     */
    public function getCharset(): ?string
    {
        return $this->charset;
    }

    /**
     * Get collation.
     *
     * @return string|null
     */
    public function getCollation(): ?string
    {
        return $this->collation;
    }

    /**
     * If not exists?
     *
     * @return bool
     */
    public function ifNotExists(): bool
    {
        return $this->ifNotExists;
    }
}
