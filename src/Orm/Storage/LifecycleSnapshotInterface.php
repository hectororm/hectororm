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

namespace Hector\Orm\Storage;

/**
 * In-memory rollback state, separate from PHP serialization or data transport.
 * Snapshots may retain object references and are restored on the same instance.
 *
 * @internal
 */
interface LifecycleSnapshotInterface
{
    /**
     * Capture owned state without loading relations or changing persistence intent.
     *
     * @return array Opaque state interpreted by the originating implementation.
     */
    public function lifecycleSnapshot(): array;

    /**
     * Restore captured state without issuing SQL or scheduling new mutations.
     *
     * @param array $snapshot State returned by lifecycleSnapshot() on this instance.
     */
    public function restoreLifecycleSnapshot(array $snapshot): void;
}
