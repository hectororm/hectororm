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

namespace Hector\Schema\Plan\Operation;

class CreateView extends AbstractViewOperation
{
    public function __construct(
        string $view,
        string $statement,
        private bool $orReplace = false,
        ?string $algorithm = null,
    ) {
        parent::__construct($view, $statement, $algorithm);
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
}
