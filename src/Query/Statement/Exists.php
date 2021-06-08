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

namespace Hector\Query\Statement;

use Closure;
use Hector\Query\StatementInterface;

/**
 * Class Exists.
 */
class Exists extends SqlFunction
{
    /**
     * Exists constructor.
     *
     * @param Closure|StatementInterface|string $expression
     */
    public function __construct(Closure|StatementInterface|string $expression)
    {
        parent::__construct('EXISTS', $expression);
    }
}