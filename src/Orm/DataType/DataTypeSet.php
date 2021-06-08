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

namespace Hector\Orm\DataType;

use Hector\Orm\DataType\DateTime\DateTimeType;
use Hector\Orm\DataType\DateTime\DateType;
use Hector\Orm\DataType\DateTime\TimestampType;
use Hector\Orm\DataType\DateTime\YearType;
use Hector\Orm\DataType\Numeric\BigIntType;
use Hector\Orm\DataType\Numeric\DecimalType;
use Hector\Orm\DataType\Numeric\DoubleType;
use Hector\Orm\DataType\Numeric\FloatType;
use Hector\Orm\DataType\Numeric\IntType;
use Hector\Orm\DataType\Numeric\MediumIntType;
use Hector\Orm\DataType\Numeric\NumericType;
use Hector\Orm\DataType\Numeric\SmallIntType;
use Hector\Orm\DataType\Numeric\TextType;
use Hector\Orm\DataType\Numeric\TinyIntType;
use Hector\Orm\DataType\String\BlobType;
use Hector\Orm\DataType\String\CharType;
use Hector\Orm\DataType\String\EnumType;
use Hector\Orm\DataType\String\LongBlobType;
use Hector\Orm\DataType\String\LongTextType;
use Hector\Orm\DataType\String\MediumBlobType;
use Hector\Orm\DataType\String\MediumTextType;
use Hector\Orm\DataType\String\SetType;
use Hector\Orm\DataType\String\TinyBlobType;
use Hector\Orm\DataType\String\TinyTextType;
use Hector\Orm\DataType\String\VarCharType;
use Hector\Orm\Exception\TypeException;
use Hector\Schema\Column;
use Hector\Schema\Exception\SchemaException;

/**
 * Class DataTypeSet.
 */
class DataTypeSet
{
    private array $types = [];
    private array $columns = [];

    /**
     * DataTypeSet constructor.
     */
    public function __construct()
    {
        $this->initDefaults();
    }

    /**
     * Init defaults.
     */
    public function initDefaults(): void
    {
        // String
        $this->addGlobalType(new CharType());
        $this->addGlobalType(new VarCharType());
        $this->addGlobalType(new TinyTextType());
        $this->addGlobalType(new TextType());
        $this->addGlobalType(new MediumTextType());
        $this->addGlobalType(new LongTextType());
        // Blob
        $this->addGlobalType(new TinyBlobType());
        $this->addGlobalType(new BlobType());
        $this->addGlobalType(new MediumBlobType());
        $this->addGlobalType(new LongBlobType());
        // Integer
        $this->addGlobalType(new TinyIntType());
        $this->addGlobalType(new SmallIntType());
        $this->addGlobalType(new MediumIntType());
        $this->addGlobalType(new IntType());
        $this->addGlobalType(new BigIntType());
        // Decimal
        $this->addGlobalType(new DecimalType());
        $this->addGlobalType(new NumericType());
        $this->addGlobalType(new FloatType());
        $this->addGlobalType(new DoubleType());
        // Date
        $this->addGlobalType(new DateType());
        $this->addGlobalType(new DateTimeType());
        $this->addGlobalType(new TimestampType());
        $this->addGlobalType(new YearType());
        // List
        $this->addGlobalType(new EnumType());
        $this->addGlobalType(new SetType());
        // Json
        $this->addGlobalType(new JsonType());
    }

    /**
     * Add global type.
     *
     * @param TypeInterface $type
     *
     * @return void
     */
    public function addGlobalType(TypeInterface $type): void
    {
        $this->types[$type::NAME] = $type;
    }

    /**
     * Add type for specific column.
     *
     * @param Column $column
     * @param TypeInterface $type
     *
     * @throws TypeException
     */
    public function addColumnType(Column $column, TypeInterface $type): void
    {
        try {
            $this->columns[$column->getFullName()] = $type;
        } catch (SchemaException $e) {
            throw new TypeException('Unable to add type for column', 0, $e);
        }
    }

    /**
     * Get type by name.
     *
     * @param string $name
     *
     * @return TypeInterface|null
     * @throws TypeException
     */
    public function getType(string $name): ?TypeInterface
    {
        if (!array_key_exists($name, $this->types)) {
            throw TypeException::unknown($name);
        }

        return $this->types[$name];
    }

    /**
     * Get type for column.
     *
     * @param Column $column
     *
     * @return TypeInterface|null
     * @throws TypeException
     */
    public function getTypeForColumn(Column $column): ?TypeInterface
    {
        try {
            if (array_key_exists($columnName = $column->getFullName(), $this->columns)) {
                return $this->columns[$columnName];
            }
        } catch (SchemaException $e) {
            throw new TypeException('Unable to find type', 0, $e);
        }

        return $this->getType($column->getType());
    }
}