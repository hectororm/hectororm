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

namespace Hector\Schema\Plan\Operation;

abstract class AbstractViewOperation implements ViewOperationInterface, PostOperationInterface
{
    public function __construct(
        private string $view,
        private string $statement,
        private ?string $algorithm = null,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getObjectName(): string
    {
        return $this->view;
    }

    /**
     * Get the SELECT statement for the view.
     *
     * @return string
     */
    public function getStatement(): string
    {
        return $this->statement;
    }

    /**
     * Get algorithm (MySQL-specific: UNDEFINED, MERGE, TEMPTABLE).
     *
     * @return string|null
     */
    public function getAlgorithm(): ?string
    {
        return $this->algorithm;
    }
}
