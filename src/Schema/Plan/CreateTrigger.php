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

use Hector\Schema\Plan\Operation\PostOperationInterface;

final class CreateTrigger implements OperationInterface, PostOperationInterface
{
    public const BEFORE = 'BEFORE';
    public const AFTER = 'AFTER';
    public const INSTEAD_OF = 'INSTEAD OF';

    public const INSERT = 'INSERT';
    public const UPDATE = 'UPDATE';
    public const DELETE = 'DELETE';

    /**
     * CreateTrigger constructor.
     *
     * @param string $table
     * @param string $name
     * @param string $timing BEFORE, AFTER or INSTEAD OF
     * @param string $event INSERT, UPDATE or DELETE
     * @param string $body SQL body of the trigger
     * @param string|null $when Optional WHEN condition (SQLite only)
     */
    public function __construct(
        private string $table,
        private string $name,
        private string $timing,
        private string $event,
        private string $body,
        private ?string $when = null,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getObjectName(): string
    {
        return $this->table;
    }

    /**
     * Get trigger name.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get timing (BEFORE, AFTER, INSTEAD OF).
     *
     * @return string
     */
    public function getTiming(): string
    {
        return $this->timing;
    }

    /**
     * Get event (INSERT, UPDATE, DELETE).
     *
     * @return string
     */
    public function getEvent(): string
    {
        return $this->event;
    }

    /**
     * Get SQL body.
     *
     * @return string
     */
    public function getBody(): string
    {
        return $this->body;
    }

    /**
     * Get WHEN condition.
     *
     * @return string|null
     */
    public function getWhen(): ?string
    {
        return $this->when;
    }
}
