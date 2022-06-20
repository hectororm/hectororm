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

declare(strict_types=1);

namespace Hector\Connection\Bind;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use ValueError;

class BindParamList implements Countable, IteratorAggregate
{
    private int $increment = 0;
    private array $parameters = [];

    public function __construct(array $values = [])
    {
        if (!empty($values)) {
            $valuesIsList = b_array_is_list($values);
            array_map(
                fn($name, $value) => $this->add(
                    value: $value,
                    name: $valuesIsList ? $name + 1 : $name
                ),
                array_keys($values),
                array_values($values)
            );
        }
    }

    /**
     * Get array copy of bind parameters.
     *
     * @return array
     */
    public function getArrayCopy(): array
    {
        return $this->parameters;
    }

    /**
     * @inheritDoc
     */
    public function getIterator(): iterable
    {
        return new ArrayIterator($this->parameters);
    }

    /**
     * @inheritDoc
     */
    public function count(): int
    {
        return count($this->parameters);
    }

    /**
     * Reset bind parameters.
     *
     * @return void
     */
    public function reset(): void
    {
        $this->parameters = [];
        $this->increment = 0;
    }

    /**
     * Add bind parameter.
     *
     * @param mixed $value
     * @param string|int|null $name
     * @param int|null $type
     *
     * @return BindParam
     */
    public function add(mixed $value, string|int|null $name = null, ?int $type = null): BindParam
    {
        if (!$value instanceof BindParam) {
            if (is_int($name) && $name <= 0) {
                throw new ValueError('Integer name of bind value must be greater than 0');
            }

            if (null === $name) {
                $name = '_h_' . $this->increment++;
            }

            $value = new BindParam($name, $value, $type);
        }

        $this->parameters[$value->getName()] = $value;

        return $value;
    }
}