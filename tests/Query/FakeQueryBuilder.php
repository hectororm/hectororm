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

namespace Hector\Query\Tests;

use Hector\Query\Delete;
use Hector\Query\Insert;
use Hector\Query\QueryBuilder;
use Hector\Query\Select;
use Hector\Query\Update;

class FakeQueryBuilder extends QueryBuilder
{
    public function makeSelect(): Select
    {
        return parent::makeSelect();
    }

    public function makeCount(): Select
    {
        return parent::makeCount();
    }

    public function makeExists(): Select
    {
        return parent::makeExists();
    }

    public function makeInsert(): Insert
    {
        return parent::makeInsert();
    }

    public function makeUpdate(): Update
    {
        return parent::makeUpdate();
    }

    public function makeDelete(): Delete
    {
        return parent::makeDelete();
    }
}