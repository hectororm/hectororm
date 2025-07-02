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

namespace Hector\Orm\Event;

trait StoppableEventTrait
{
    private bool $stopped = false;

    /**
     * Stop propagation of event.
     */
    public function stopPropagation(): void
    {
        $this->stopped = true;
    }

    /**
     * Is propagation stopped?
     *
     * @return bool
     */
    public function isPropagationStopped(): bool
    {
        return $this->stopped;
    }
}