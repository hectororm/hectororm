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

namespace Hector\Orm\Storage;

use FilterIterator;
use WeakReference;

class FilterStorageIterator extends FilterIterator
{
    /**
     * FilterStorageIterator constructor.
     *
     * @param StorageIterator $iterator
     */
    public function __construct(StorageIterator $iterator)
    {
        parent::__construct($iterator);
    }

    /**
     * @inheritDoc
     */
    public function accept(): bool
    {
        $current = $this->getInnerIterator()->current();

        if (!$current instanceof WeakReference) {
            return false;
        }

        if (null === $current->get()) {
            return false;
        }

        return true;
    }

    /**
     * Get current Entity.
     *
     * @return mixed
     */
    public function current(): mixed
    {
        $weakReference = parent::current();

        return $weakReference->get();
    }
}
