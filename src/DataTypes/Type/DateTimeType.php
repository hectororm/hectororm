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

namespace Hector\DataTypes\Type;

use DateTime;
use DateTimeInterface;
use Hector\DataTypes\ExpectedType;
use Hector\DataTypes\TypeException;
use Throwable;

/**
 * Class DateTimeType.
 */
class DateTimeType extends AbstractType
{
    public function __construct(
        protected string $format = 'Y-m-d H:i:s'
    ) {
    }

    /**
     * @inheritDoc
     */
    public function fromSchema(mixed $value, ?ExpectedType $expected = null): mixed
    {
        try {
            if (null === $expected) {
                return new DateTime($value);
            }

            if ($expected->getName() == 'string') {
                return (string)$value;
            }

            if ($expected->getName() == 'int') {
                return (int)strtotime((string)$value);
            }

            if (is_a($expected->getName(), DateTimeInterface::class, true)) {
                $class = $expected->getName();

                return new $class((string)$value);
            }
        } catch (Throwable $e) {
            throw TypeException::castError($this, $e);
        }

        throw TypeException::castError($this);
    }

    /**
     * @inheritDoc
     */
    public function toSchema(mixed $value, ?ExpectedType $expected = null): string
    {
        try {
            if (is_string($value)) {
                $value = new DateTime($value);
            }

            if (is_numeric($value)) {
                $value = new DateTime(sprintf('@%d', $value));
            }

            if ($value instanceof DateTimeInterface) {
                return $value->format($this->format);
            }

            throw TypeException::castError($this);
        } catch (TypeException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw TypeException::castError($this, $exception);
        }
    }
}