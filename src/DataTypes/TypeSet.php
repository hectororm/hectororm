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

namespace Hector\DataTypes;

use Countable;
use UnexpectedValueException;

class TypeSet implements Countable
{
    private array $types = [];

    /**
     * TypeSet constructor.
     */
    public function __construct(array $types = [])
    {
        $this->reset();
        array_walk($types, fn(Type\TypeInterface $object, string $type) => $this->add($type, $object));
    }

    /**
     * @inheritDoc
     */
    public function count(): int
    {
        return count($this->types);
    }

    /**
     * Reset.
     */
    public function reset(): void
    {
        $this->types = [];

        $stringType = new Type\StringType();
        $intType = new Type\NumericType('int');
        $floatType = new Type\NumericType('float');
        $dateTimeType = new Type\DateTimeType();

        // String
        $this->add('char', $stringType);
        $this->add('varchar', $stringType);
        $this->add('tinytext', $stringType);
        $this->add('text', $stringType);
        $this->add('mediumtext', $stringType);
        $this->add('longtext', $stringType);
        // Blob
        $this->add('tinyblob', $stringType);
        $this->add('blob', $stringType);
        $this->add('mediumblob', $stringType);
        $this->add('longblog', $stringType);
        // Integer
        $this->add('tinyint', $intType);
        $this->add('smallint', $intType);
        $this->add('mediumint', $intType);
        $this->add('int', $intType);
        $this->add('bigint', $intType);
        // Decimal
        $this->add('decimal', $floatType);
        $this->add('numeric', $floatType);
        $this->add('float', $floatType);
        $this->add('double', $floatType);
        // Date
        $this->add('date', new Type\DateTimeType('Y-m-d'));
        $this->add('datetime', $dateTimeType);
        $this->add('timestamp', $dateTimeType);
        $this->add('year', $intType);
        // List
        $this->add('enum', $stringType);
        $this->add('set', new Type\SetType());
        // Json
        $this->add('json', new Type\JsonType());
    }

    /**
     * Add type.
     *
     * @param string $type
     * @param Type\TypeInterface $object
     *
     * @return void
     */
    public function add(string $type, Type\TypeInterface $object): void
    {
        $this->types[$type] = $object;
    }

    /**
     * Get type by name.
     *
     * @param string $type
     *
     * @return Type\TypeInterface
     */
    public function get(string $type): Type\TypeInterface
    {
        if (!array_key_exists($type, $this->types)) {
            throw new UnexpectedValueException(sprintf('Type "%s" not declared in Hector', $type));
        }

        return $this->types[$type];
    }
}
