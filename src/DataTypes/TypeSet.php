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
use Hector\DataTypes\Type;

/**
 * Class TypeSet.
 */
class TypeSet implements Countable
{
    private array $types = [];

    /**
     * TypeSet constructor.
     */
    public function __construct()
    {
        $this->reset();
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

        // String
        $this->add('char', new Type\StringType());
        $this->add('varchar', new Type\StringType());
        $this->add('tinytext', new Type\StringType());
        $this->add('text', new Type\StringType());
        $this->add('mediumtext', new Type\StringType());
        $this->add('longtext', new Type\StringType());
        // Blob
        $this->add('tinyblob', new Type\StringType());
        $this->add('blob', new Type\StringType());
        $this->add('mediumblob', new Type\StringType());
        $this->add('longblog', new Type\StringType());
        // Integer
        $this->add('tinyint', new Type\NumericType('int'));
        $this->add('smallint', new Type\NumericType('int'));
        $this->add('mediumint', new Type\NumericType('int'));
        $this->add('int', new Type\NumericType('int'));
        $this->add('bigint', new Type\NumericType('int'));
        // Decimal
        $this->add('decimal', new Type\NumericType('float'));
        $this->add('numeric', new Type\NumericType('float'));
        $this->add('float', new Type\NumericType('float'));
        $this->add('double', new Type\NumericType('float'));
        // Date
        $this->add('date', new Type\DateTimeType('Y-m-d'));
        $this->add('datetime', new Type\DateTimeType());
        $this->add('timestamp', new Type\DateTimeType());
        $this->add('year', new Type\NumericType('int'));
        // List
        $this->add('enum', new Type\StringType());
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
     * @throws TypeException
     */
    public function get(string $type): Type\TypeInterface
    {
        if (!array_key_exists($type, $this->types)) {
            throw TypeException::unknown($type);
        }

        return $this->types[$type];
    }
}