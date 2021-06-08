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

namespace Hector\Orm\Collection;

use FilterIterator;
use Iterator;

/**
 * Class FilterCollectionIterator.
 */
class FilterCollectionIterator extends FilterIterator
{
    private $callback;

    /**
     * FilterCollectionIterator constructor.
     *
     * @param Iterator $iterator
     * @param callable $callback
     */
    public function __construct(Iterator $iterator, callable $callback)
    {
        parent::__construct($iterator);
        $this->callback = $callback;
    }

    /**
     * @inheritDoc
     */
    public function accept(): bool
    {
        return call_user_func($this->callback, $this->getInnerIterator()->current());
    }
}