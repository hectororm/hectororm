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

namespace Hector\Orm\Tests\Collection;

use Hector\Orm\Collection\Collection;
use Hector\Orm\Tests\Fake\Entity\Film;

class CollectionWithHook extends Collection
{
    private mixed $hookValue = null;

    public function __construct(iterable $input = [])
    {
        parent::__construct($input, Film::class);
    }

    protected function updateHook(): void
    {
        $this->hookValue = implode(', ', array_column($this->getArrayCopy(), 'title'));
    }

    public function getHookValue(): mixed
    {
        return $this->hookValue;
    }
}