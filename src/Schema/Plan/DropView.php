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

final class DropView implements OperationInterface
{
    /**
     * DropView constructor.
     *
     * @param string $view
     * @param bool $ifExists
     */
    public function __construct(
        private string $view,
        private bool $ifExists = false,
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
     * If exists?
     *
     * @return bool
     */
    public function ifExists(): bool
    {
        return $this->ifExists;
    }
}
