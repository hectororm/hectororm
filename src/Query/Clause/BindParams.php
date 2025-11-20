<?php
/*
 * This file is part of Hector ORM.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2022 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

namespace Hector\Query\Clause;

use Hector\Connection\Bind\BindParam;
use Hector\Connection\Bind\BindParamList;

trait BindParams
{
    private BindParamList $binds;

    /**
     * Reset assignments.
     *
     * @return static
     */
    public function resetBindParams(): static
    {
        $this->binds = new BindParamList();

        return $this;
    }

    /**
     * Bind value.
     *
     * @param string $name
     * @param BindParam|mixed $value
     * @param int|null $type
     *
     * @return $this
     */
    public function bind(string $name, mixed $value, ?int $type = null): static
    {
        $this->getBindParams()->add($value, $name, $type);

        return $this;
    }

    /**
     * Get bind params.
     *
     * @return BindParamList
     */
    public function getBindParams(): BindParamList
    {
        return $this->binds;
    }

    /**
     * Merge bind parameters.
     *
     * @param BindParamList $binds
     *
     * @return void
     */
    private function mergeBindParamsTo(BindParamList $binds): void
    {
        array_map(
            fn(BindParam $param): BindParam => $binds->add($param),
            $this->getBindParams()->getArrayCopy(),
        );
    }
}
