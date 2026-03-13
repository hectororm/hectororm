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

final class CreateView implements OperationInterface
{
    /**
     * CreateView constructor.
     *
     * @param string $view
     * @param string $statement SQL SELECT statement
     * @param bool $orReplace
     * @param string|null $algorithm MySQL-specific algorithm (UNDEFINED, MERGE, TEMPTABLE)
     */
    public function __construct(
        private string $view,
        private string $statement,
        private bool $orReplace = false,
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
     * OR REPLACE clause?
     *
     * @return bool
     */
    public function orReplace(): bool
    {
        return $this->orReplace;
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
